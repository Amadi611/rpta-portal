<?php
require_once __DIR__.'/../config.php';
require_login();
if(!in_array(role(),['admin','data_entry','super_admin'],true)) redirect('../dashboard.php');

if(empty($_SESSION['events_csrf'])) $_SESSION['events_csrf']=bin2hex(random_bytes(32));
$error='';
$uploadDirectory=__DIR__.'/../uploads/events';

function remove_event_image($path){
	if(!preg_match('#^uploads/events/event_[a-f0-9]{24}\.(jpg|png|webp)$#',$path)) return;
	$file=__DIR__.'/../'.$path;
	if(is_file($file)) @unlink($file);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
	$newImagePath=null;
	$oldImagePath='';
	try{
		if(!hash_equals($_SESSION['events_csrf'],$_POST['csrf_token']??'')) throw new RuntimeException('Your session expired. Refresh the page and try again.');
		$id=(int)($_POST['id']??0);
		if(($_POST['action']??'save')==='delete'){
			$find=$conn->prepare('SELECT image_path FROM events WHERE id=? LIMIT 1');
			$find->bind_param('i',$id);$find->execute();$existing=$find->get_result()->fetch_assoc();
			if(!$existing) throw new RuntimeException('Event not found.');
			$delete=$conn->prepare('DELETE FROM events WHERE id=?');$delete->bind_param('i',$id);
			if(!$delete->execute()) throw new RuntimeException('Could not delete this event.');
			remove_event_image($existing['image_path']);
			header('Location: events.php?deleted=1');exit;
		}

		$title=trim($_POST['title']??'');
		$description=trim($_POST['description']??'');
		$dateInput=trim($_POST['event_date']??'');
		$active=isset($_POST['active'])?1:0;
		if($title==='' || strlen($title)>180) throw new RuntimeException('Enter an event title up to 180 characters.');
		$eventDate=null;
		if($dateInput!==''){
			$date=DateTime::createFromFormat('!Y-m-d',$dateInput);
			if(!$date || $date->format('Y-m-d')!==$dateInput) throw new RuntimeException('Enter a valid event date.');
			$eventDate=$dateInput;
		}
		if($id){
			$find=$conn->prepare('SELECT image_path FROM events WHERE id=? LIMIT 1');
			$find->bind_param('i',$id);$find->execute();$existing=$find->get_result()->fetch_assoc();
			if(!$existing) throw new RuntimeException('Event not found.');
			$oldImagePath=$existing['image_path'];
		}
		$upload=$_FILES['event_photo']??null;
		if($upload && $upload['error']!==UPLOAD_ERR_NO_FILE){
			$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
			if($upload['error']!==UPLOAD_ERR_OK || $upload['size']>8388608) throw new RuntimeException('Choose an image smaller than 8 MB.');
			$finfo=new finfo(FILEINFO_MIME_TYPE);
			$mime=$finfo->file($upload['tmp_name']);
			$imageInfo=@getimagesize($upload['tmp_name']);
			if(!isset($allowed[$mime]) || !$imageInfo || ($imageInfo['mime']??'')!==$mime) throw new RuntimeException('Upload a JPG, PNG, or WebP photo.');
			if(!is_dir($uploadDirectory) && !mkdir($uploadDirectory,0755,true) && !is_dir($uploadDirectory)) throw new RuntimeException('Could not prepare the photo directory.');
			$filename='event_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
			$newImagePath='uploads/events/'.$filename;
			if(!move_uploaded_file($upload['tmp_name'],$uploadDirectory.DIRECTORY_SEPARATOR.$filename)) throw new RuntimeException('Could not store the uploaded photo.');
		}elseif(!$id){
			throw new RuntimeException('Choose an event photo to upload.');
		}
		$imagePath=$newImagePath??$oldImagePath;
		if($id){
			$save=$conn->prepare('UPDATE events SET title=?,description=?,event_date=?,image_path=?,active=? WHERE id=?');
			$save->bind_param('ssssii',$title,$description,$eventDate,$imagePath,$active,$id);
		}else{
			$save=$conn->prepare('INSERT INTO events(title,description,event_date,image_path,active) VALUES(?,?,?,?,?)');
			$save->bind_param('ssssi',$title,$description,$eventDate,$imagePath,$active);
		}
		if(!$save || !$save->execute()) throw new RuntimeException('Could not save the event.');
		if($newImagePath && $oldImagePath) remove_event_image($oldImagePath);
		header('Location: events.php?saved=1');exit;
	}catch(Throwable $exception){
		if($newImagePath) remove_event_image($newImagePath);
		$error=$exception->getMessage();
	}
}

$editId=(int)($_GET['edit']??0);$editEvent=null;
if($editId){$edit=$conn->prepare('SELECT * FROM events WHERE id=? LIMIT 1');$edit->bind_param('i',$editId);$edit->execute();$editEvent=$edit->get_result()->fetch_assoc();}
$events=$conn->query('SELECT * FROM events ORDER BY COALESCE(event_date,"9999-12-31") ASC,id DESC');
$pageStyles=['assets/events.css'];
$title='Manage Events';
include __DIR__.'/../includes/header.php';
?>
<div class="page">
	<div class="section-title"><div><span class="badge">EVENTS</span><h2>Manage Events</h2><p class="muted">Upload event photos and choose which announcements appear on the home page.</p></div><a class="secondary-btn" href="../dashboard.php">← Dashboard</a></div>
	<?php if(isset($_GET['saved'])):?><div class="notice success" role="status">Event saved.</div><?php endif;?>
	<?php if(isset($_GET['deleted'])):?><div class="notice success" role="status">Event deleted.</div><?php endif;?>
	<?php if($error):?><div class="notice danger" role="alert"><?=h($error)?></div><?php endif;?>
	<div class="event-admin-layout">
		<section class="form-card event-editor"><h3><?=$editEvent?'Edit Event':'Add Event'?></h3>
			<form method="post" enctype="multipart/form-data">
				<input type="hidden" name="csrf_token" value="<?=h($_SESSION['events_csrf'])?>">
				<input type="hidden" name="id" value="<?=(int)($editEvent['id']??0)?>">
				<label for="eventTitle">Event title *</label><input id="eventTitle" name="title" maxlength="180" value="<?=h($editEvent['title']??'')?>" required>
				<label for="eventDate">Event date</label><input id="eventDate" type="date" name="event_date" value="<?=h($editEvent['event_date']??'')?>">
				<label for="eventDescription">Details</label><textarea id="eventDescription" name="description" rows="4"><?=h($editEvent['description']??'')?></textarea>
				<label for="eventPhoto">Event photo <?=$editEvent?'(optional when keeping current photo)':'*'?></label><input id="eventPhoto" type="file" name="event_photo" accept="image/jpeg,image/png,image/webp" <?=$editEvent?'':'required'?>><p class="muted">JPG, PNG, or WebP. Maximum 8 MB.</p>
				<?php if($editEvent):?><img class="event-current-photo" src="../<?=h($editEvent['image_path'])?>" alt="Current event photo"><?php endif;?>
				<label class="choice-options event-active"><input type="checkbox" name="active" value="1" <?=!isset($editEvent['active']) || (int)$editEvent['active']===1?'checked':''?>> Show on home page</label>
				<div class="btn-row"><button class="primary-btn" type="submit">Save Event</button><?php if($editEvent):?><a class="secondary-btn" href="events.php">Cancel</a><?php endif;?></div>
			</form>
		</section>
		<section class="event-list"><h3>Events <span><?= (int)$events->num_rows ?></span></h3>
			<?php if($events->num_rows===0):?><p class="event-list-empty">No events have been added.</p><?php else:while($event=$events->fetch_assoc()):?>
				<article class="event-admin-row"><img src="../<?=h($event['image_path'])?>" alt=""><div class="event-admin-copy"><strong><?=h($event['title'])?></strong><span><?=h($event['event_date']?:'Date not set')?> · <?=$event['active']?'Shown on home page':'Hidden'?></span></div><a class="table-link" href="?edit=<?=(int)$event['id']?>">Edit</a><form method="post" onsubmit="return confirm('Delete this event and its photo?')"><input type="hidden" name="csrf_token" value="<?=h($_SESSION['events_csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$event['id']?>"><button class="link-danger" type="submit">Delete</button></form></article>
			<?php endwhile;endif;?>
		</section>
	</div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>