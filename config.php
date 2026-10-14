<?php
if (session_status() === PHP_SESSION_NONE) session_start();
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'rpta_portal';
const BASE_URL = '/rpta_portal';
const APP_NAME_EN = 'Southern Provincial Road Passenger Transport Authority';
const APP_NAME_SI = 'දකුණු පළාත් මාර්ගස්ථ මගී ප්‍රවාහන අධිකාරිය';
const CONTACT_PHONE = '0912234231';
const BUS_PHOTO_URL = 'https://upload.wikimedia.org/wikipedia/commons/2/20/LANKA_ASHOK_LEYLAND_PUBLIC_BUS_AT_GALLE_RAILWAY_STATION_SRI_LANKA_JAN_2013_%288553472957%29.jpg';
const OFFICIAL_LOGO_URL = 'https://www.sprpta.lk/sms/images/sprpta_logo.png';
const OFFICIAL_LOGO_FALLBACK_URL = '/rpta_portal/assets/images/rpta_official_logo.png';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_errno) {
    $bootstrap = @new mysqli(DB_HOST, DB_USER, DB_PASS);
    if (!$bootstrap->connect_errno) {
        $bootstrap->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $bootstrap->close();
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
}
if (!$conn || $conn->connect_errno) {
    die('<div style="font-family:Arial;padding:30px;background:#fff8f8;color:#7b1530"><h2>Database connection problem</h2><p>Please start MySQL in XAMPP and refresh.</p></div>');
}
$conn->set_charset('utf8mb4');
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect($p){ header('Location: '.BASE_URL.'/'.ltrim($p,'/')); exit; }
function logged_in(){ return !empty($_SESSION['user']); }
function role(){ return $_SESSION['user']['role'] ?? null; }
function require_login(){ if(!logged_in()) redirect('login.php'); }
function is_staff(){ return in_array(role(), ['admin','control_admin','driving_admin','accounts_admin','super_admin','data_entry'], true); }
function can_manage_accounts(){ return in_array(role(), ['admin','super_admin','data_entry'], true); }
function can($r){ return role()==='super_admin' || role()===$r; }
function staff_department(){
    return ['control_admin'=>'control','driving_admin'=>'driving','accounts_admin'=>'accounts'][role()] ?? null;
}
function department_tasks(){
    return [
        'control'=>['Employee management','Bus management','Route management','License management','Complaint management','User management','Reports and information'],
        'driving'=>['Bus routes','Bus timetables','Bus stops','Runs Times','Bus services','Route changes','Running problems'],
        'accounts'=>['Fee','Payments','License fees','Fines','Revenue','Payment receipts']
    ];
}
function lang(){ return $_SESSION['lang'] ?? 'si'; }
if(isset($_GET['lang']) && in_array($_GET['lang'],['si','en'],true)) $_SESSION['lang']=$_GET['lang'];

$tables = [
"CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,full_name VARCHAR(120) NOT NULL,username VARCHAR(80) UNIQUE NOT NULL,password_hash VARCHAR(255) NOT NULL,role ENUM('passenger','data_entry','admin','control_admin','driving_admin','accounts_admin','super_admin','officer_registrar') NOT NULL DEFAULT 'passenger',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS passenger_profiles(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT UNIQUE NOT NULL,nic VARCHAR(30) NOT NULL,phone VARCHAR(30) NOT NULL,email VARCHAR(120) NULL,address VARCHAR(255) NULL,district VARCHAR(80) NULL,customer_type VARCHAR(80) NULL,department VARCHAR(40) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS active_permits(id INT AUTO_INCREMENT PRIMARY KEY,permit_number VARCHAR(40) NULL,registration_number VARCHAR(40) NULL,permit_status VARCHAR(40) NULL,owner_name VARCHAR(150) NULL,owner_nic VARCHAR(30) NULL,owner_phone VARCHAR(30) NULL,driver_name VARCHAR(150) NULL,driver_phone VARCHAR(30) NULL,conductor_name VARCHAR(150) NULL,conductor_phone VARCHAR(30) NULL,KEY idx_active_permits_permit(permit_number),KEY idx_active_permits_registration(registration_number)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS officer_profiles(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT UNIQUE NOT NULL,nic VARCHAR(30) NOT NULL,phone VARCHAR(30) NOT NULL,email VARCHAR(120) NULL,position VARCHAR(120) NOT NULL,department ENUM('control','accounts','driving') NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS services(id INT AUTO_INCREMENT PRIMARY KEY,name_en VARCHAR(180) NOT NULL,name_si VARCHAR(180) NOT NULL,category VARCHAR(80) NOT NULL,active TINYINT(1) DEFAULT 1) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS vehicles(id INT AUTO_INCREMENT PRIMARY KEY,vehicle_type VARCHAR(80) NOT NULL,registration_no VARCHAR(40) NOT NULL,operator_name VARCHAR(150) NULL,route VARCHAR(160) NULL,status VARCHAR(40) DEFAULT 'Active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS bus_stands(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(160) NOT NULL,district VARCHAR(80) NOT NULL,address VARCHAR(255) NULL,status VARCHAR(40) DEFAULT 'Active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS chat_sessions(id INT AUTO_INCREMENT PRIMARY KEY,session_key VARCHAR(100) NOT NULL,user_id INT NULL,started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,ended_at DATETIME NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS chat_messages(id INT AUTO_INCREMENT PRIMARY KEY,chat_session_id INT NOT NULL,sender ENUM('user','bot') NOT NULL,message TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS feedback(id INT AUTO_INCREMENT PRIMARY KEY,chat_session_id INT NULL,user_id INT NULL,token_id INT NULL,rating TINYINT NOT NULL,note VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS client_notifications(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,token_id INT NOT NULL,notification_type VARCHAR(40) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_client_notifications_token_type(token_id,notification_type),KEY idx_client_notifications_user_created(user_id,created_at)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS complaints(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,category VARCHAR(80) NOT NULL,bus_number VARCHAR(40) NULL,route VARCHAR(160) NULL,service_date DATE NULL,service_time TIME NULL,location VARCHAR(200) NULL,contact_phone VARCHAR(30) NOT NULL,contact_email VARCHAR(120) NULL,description TEXT NOT NULL,status ENUM('received','reviewing','resolved') NOT NULL DEFAULT 'received',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS tokens(id INT AUTO_INCREMENT PRIMARY KEY,token_no VARCHAR(40) UNIQUE NOT NULL,user_id INT NULL,full_name VARCHAR(120) NOT NULL,nic VARCHAR(30) NOT NULL,phone VARCHAR(30) NOT NULL,email VARCHAR(120) NULL,address VARCHAR(255) NULL,district VARCHAR(80) NULL,bus_number VARCHAR(40) NULL,route VARCHAR(160) NULL,vehicle_type VARCHAR(80) NULL,vehicle_no VARCHAR(40) NULL,bus_stand VARCHAR(160) NULL,service_type VARCHAR(160) NOT NULL,department VARCHAR(40) NULL,preferred_date DATE NOT NULL,preferred_time VARCHAR(20) NOT NULL,purpose VARCHAR(255) NULL,additional_details TEXT NULL,status ENUM('pending','issued','completed','cancelled') DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS documents(id INT AUTO_INCREMENT PRIMARY KEY,title_en VARCHAR(200) NOT NULL,title_si VARCHAR(200) NOT NULL,file_name VARCHAR(255) NOT NULL,file_path VARCHAR(255) NOT NULL,active TINYINT(1) DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS events(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(180) NOT NULL,description TEXT NULL,event_date DATE NULL,image_path VARCHAR(255) NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS faqs(id INT AUTO_INCREMENT PRIMARY KEY,intent VARCHAR(100) NOT NULL,question TEXT NOT NULL,answer TEXT NOT NULL,language ENUM('en','si') NOT NULL,active TINYINT(1) DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"
];
foreach($tables as $sql) $conn->query($sql);

@$conn->query("ALTER TABLE users MODIFY role ENUM('passenger','data_entry','admin','control_admin','driving_admin','accounts_admin','super_admin','officer_registrar') NOT NULL DEFAULT 'passenger'");
@$conn->query("ALTER TABLE passenger_profiles ADD COLUMN customer_type VARCHAR(80) NULL");
@$conn->query("ALTER TABLE passenger_profiles ADD COLUMN department VARCHAR(40) NULL");
@$conn->query("ALTER TABLE tokens ADD COLUMN department VARCHAR(40) NULL");
@$conn->query("ALTER TABLE tokens MODIFY status ENUM('pending','issued','completed','cancelled') DEFAULT 'pending'");
@$conn->query("ALTER TABLE feedback ADD COLUMN token_id INT NULL");

// Upgrade/repair columns used by the current portal when an older database already exists.
$schemaRepairs = [
    "ALTER TABLE services ADD COLUMN active TINYINT(1) DEFAULT 1",
    "ALTER TABLE vehicles ADD COLUMN status VARCHAR(40) DEFAULT 'Active'",
    "ALTER TABLE bus_stands ADD COLUMN status VARCHAR(40) DEFAULT 'Active'",
    "ALTER TABLE documents ADD COLUMN active TINYINT(1) DEFAULT 1",
    "ALTER TABLE faqs ADD COLUMN active TINYINT(1) DEFAULT 1",
    "ALTER TABLE tokens ADD COLUMN status ENUM('pending','issued','completed','cancelled') DEFAULT 'issued'",
    "ALTER TABLE tokens ADD COLUMN address VARCHAR(255) NULL",
    "ALTER TABLE tokens ADD COLUMN district VARCHAR(80) NULL",
    "ALTER TABLE feedback ADD COLUMN service_area VARCHAR(80) NULL",
    "ALTER TABLE feedback ADD COLUMN recommend ENUM('yes','no') NULL",
    "ALTER TABLE feedback ADD COLUMN service_date DATE NULL",
    "ALTER TABLE passenger_profiles ADD COLUMN permit_number VARCHAR(40) NULL",
    "ALTER TABLE passenger_profiles ADD COLUMN vehicle_registration VARCHAR(40) NULL"
];
foreach($schemaRepairs as $sql) @$conn->query($sql);
@$conn->query("UPDATE services SET active=1 WHERE active IS NULL");
@$conn->query("UPDATE vehicles SET status='Active' WHERE status IS NULL OR status=''");
@$conn->query("UPDATE bus_stands SET status='Active' WHERE status IS NULL OR status=''");
@$conn->query("UPDATE documents SET active=1 WHERE active IS NULL");
@$conn->query("UPDATE faqs SET active=1 WHERE active IS NULL");

// Backward-compatible repairs for older databases.
$alters=[
 "ALTER TABLE tokens ADD COLUMN user_id INT NULL AFTER token_no",
 "ALTER TABLE tokens ADD COLUMN vehicle_type VARCHAR(80) NULL AFTER route",
 "ALTER TABLE tokens ADD COLUMN vehicle_no VARCHAR(40) NULL AFTER vehicle_type",
 "ALTER TABLE tokens ADD COLUMN bus_stand VARCHAR(160) NULL AFTER vehicle_no",
 "ALTER TABLE tokens ADD COLUMN additional_details TEXT NULL AFTER purpose"
];
foreach($alters as $sql) @$conn->query($sql);

// Repair passenger profile columns if an older RPTA database already exists.
// This prevents mysqli prepare() failures when upgrading from an earlier ZIP.
$profileColumns = [
    'user_id' => 'INT NULL',
    'nic' => 'VARCHAR(30) NULL',
    'phone' => 'VARCHAR(30) NULL',
    'email' => 'VARCHAR(120) NULL',
    'address' => 'VARCHAR(255) NULL',
    'district' => 'VARCHAR(80) NULL'
];
foreach ($profileColumns as $col => $definition) {
    $safeCol = $conn->real_escape_string($col);
    $exists = $conn->query("SHOW COLUMNS FROM passenger_profiles LIKE '$safeCol'");
    if (!$exists || $exists->num_rows === 0) {
        @$conn->query("ALTER TABLE passenger_profiles ADD COLUMN `$safeCol` $definition");
    }
}
// Existing records may contain NULL values after the repair; new registrations require these fields.
@$conn->query("ALTER TABLE passenger_profiles MODIFY nic VARCHAR(30) NOT NULL");
@$conn->query("ALTER TABLE passenger_profiles MODIFY phone VARCHAR(30) NOT NULL");

$faqCount=(int)($conn->query("SELECT COUNT(*) c FROM faqs")->fetch_assoc()['c'] ?? 0);
if($faqCount===0){
    $faqFile=__DIR__.'/sql/faqs_400.sql';
    if(is_file($faqFile)){ $sql400=@file_get_contents($faqFile); if($sql400) @$conn->multi_query($sql400); while($conn->more_results() && $conn->next_result()){} }
}
// Seed staff accounts only when they do not already exist, preserving user-selected credentials.
$demoHash=password_hash('password', PASSWORD_DEFAULT);
$users=[
 ['System Super Administrator','superadmin','super_admin'],
 ['Portal Administrator','admin','admin'],
 ['Control Section Administrator','controladmin','control_admin'],
 ['Driving Section Administrator','drivingadmin','driving_admin'],
 ['Accounts Section Administrator','accountsadmin','accounts_admin'],
 ['Content Data Entry','dataentry','data_entry'],
 ['Officer Registration','rpta','officer_registrar']
];
$stmt=$conn->prepare("INSERT IGNORE INTO users(full_name,username,password_hash,role) VALUES(?,?,?,?)");
if($stmt){ foreach($users as $u){$seedHash=$u[1]==='rpta'?password_hash('rpta',PASSWORD_DEFAULT):$demoHash;$stmt->bind_param('ssss',$u[0],$u[1],$seedHash,$u[2]);$stmt->execute();} }
$rptaLookup=$conn->prepare('SELECT role FROM users WHERE username=? LIMIT 1');
$rptaUsername='rpta';
$rptaLookup->bind_param('s',$rptaUsername); $rptaLookup->execute();
$rptaAccount=$rptaLookup->get_result()->fetch_assoc();
if($rptaAccount && $rptaAccount['role']!=='officer_registrar'){
    $repairHash=password_hash('rpta',PASSWORD_DEFAULT);
    $repair=$conn->prepare("UPDATE users SET full_name='Officer Registration',password_hash=?,role='officer_registrar' WHERE username=?");
    $repair->bind_param('ss',$repairHash,$rptaUsername); $repair->execute();
}

$seedServices=[
 ['Passenger information / inquiry','මගී තොරතුරු / විමසීම්','Passenger Information'],
 ['Public complaint','මහජන පැමිණිල්ල','Complaints'],
 ['Operator complaint','බස් හිමිකරු / මෙහෙයුම්කරු පැමිණිල්ල','Complaints'],
 ['Route permit / new passenger service','නව මාර්ග බලපත්‍ර / මගී සේවා','Permits'],
 ['Route permit renewal','මාර්ග බලපත්‍ර අලුත් කිරීම','Permits'],
 ['Passenger service permit information','මගී සේවා අවසර පත්‍ර තොරතුරු','Permits'],
 ['Bus timetable information','බස් කාලසටහන් තොරතුරු','Timetables'],
 ['Bus fare information','බස් ගාස්තු තොරතුරු','Timetables & Fares'],
 ['Route / route map information','මාර්ග / මාර්ග සිතියම් තොරතුරු','Route Information'],
 ['Bus stand service','බස් නැවතුම් සේවා','Infrastructure'],
 ['Bus shelter service','බස් මගී ආවරණ / නැවතුම් සේවා','Infrastructure'],
 ['Bus halt place information','බස් හෝල්ට් ස්ථාන තොරතුරු','Infrastructure'],
 ['SLTB / CTB bus matter','SLTB / CTB බස් සම්බන්ධ කරුණ','State Bus'],
 ['Private bus matter','පෞද්ගලික බස් සම්බන්ධ කරුණ','Private Bus'],
 ['Three-wheeler passenger transport permit','මගී ප්‍රවාහන ත්‍රීරෝද රථ බලපත්‍ර','Three-Wheeler'],
 ['School children transport service','පාසල් ළමුන් ප්‍රවාහන සේවාව','School Transport'],
 ['Driver training','රියදුරු පුහුණුව','Training'],
 ['Conductor training','කොන්දොස්තර පුහුණුව','Training'],
 ['Point controller training','Point Controller පුහුණුව','Training'],
 ['Schedules officer training','Schedules Officer පුහුණුව','Training'],
 ['Passenger safety program','මගී ආරක්ෂණ වැඩසටහන','Safety'],
 ['Transport professional training / certificates','ප්‍රවාහන වෘත්තීය පුහුණු / සහතික','Training'],
 ['Bus service / route information','බස් සේවා / මාර්ග තොරතුරු','Passenger Information'],
 ['Other authority service','වෙනත් අධිකාරි සේවාවක්','Other']
];
if((int)($conn->query("SELECT COUNT(*) c FROM services")->fetch_assoc()['c']??0)===0){$s=$conn->prepare("INSERT INTO services(name_en,name_si,category,active) VALUES(?,?,?,1)");if($s){foreach($seedServices as $x){$s->bind_param('sss',$x[0],$x[1],$x[2]);$s->execute();}}} else { @$conn->query("UPDATE services SET active=1 WHERE active IS NULL OR active=0"); }
$seedStands=[['Galle Bus Stand','Galle','Galle District'],['Matara Bus Stand','Matara','Matara District'],['Hambantota Bus Stand','Hambantota','Hambantota District'],['Tangalle Bus Stand','Hambantota','Hambantota District'],['Ambalangoda Bus Stand','Galle','Galle District'],['Akuressa Bus Stand','Matara','Matara District']];
if((int)($conn->query("SELECT COUNT(*) c FROM bus_stands")->fetch_assoc()['c']??0)===0){$s=$conn->prepare("INSERT INTO bus_stands(name,district,address) VALUES(?,?,?)");foreach($seedStands as $x){$s->bind_param('sss',$x[0],$x[1],$x[2]);$s->execute();}}
$seedVehicles=[['SLTB Bus','NA-1234','Sri Lanka Transport Board','Galle - Matara'],['Private Bus','NB-5678','Demo Private Operator','Matara - Galle'],['Three-Wheeler','WP-3456','Demo Passenger Service','Galle'],['School Van','NC-7890','Demo School Transport','Matara']];
if((int)($conn->query("SELECT COUNT(*) c FROM vehicles")->fetch_assoc()['c']??0)===0){$s=$conn->prepare("INSERT INTO vehicles(vehicle_type,registration_no,operator_name,route) VALUES(?,?,?,?)");foreach($seedVehicles as $x){$s->bind_param('ssss',$x[0],$x[1],$x[2],$x[3]);$s->execute();}}
$docs=[
 ['Conductor License Application','කොන්දොස්තර බලපත්‍ර අයදුම්පත','Conductor_License_Application.pdf'],
 ['Conductor Medical Examination Report','කොන්දොස්තර වෛද්‍ය පරීක්ෂණ වාර්තාව','Conductor_Medical_Examination_Report.pdf'],
 ['Driver Medical Examination Report','රියදුරු වෛද්‍ය පරීක්ෂණ වාර්තාව','Drivers_Medical_Examination_Report.pdf'],
 ['Omnibus Fitness Report','බස් රථ යෝග්‍යතා වාර්තාව','Omini_Bus_Fitness_Report.pdf'],
 ['Route Permit Extension Application','මාර්ග බලපත්‍ර දිගු කිරීමේ අයදුම්පත','Route_Permit_Extend_Application.pdf'],
 ['Route Permit Transfer Request Application (Relatives)','මාර්ග බලපත්‍රය ඥාතියෙකුට පැවරීමේ ඉල්ලුම්පත','Route_Permit_Transfer_Request_Application-Relatives.pdf'],
 ['Route Permit Transfer Request Application','මාර්ග බලපත්‍ර පැවරීමේ ඉල්ලුම්පත','Route_permit_Transfer_Request_Application.pdf']
];
@$conn->query("UPDATE documents SET active=0 WHERE file_name IN ('passenger_complaint_form.pdf','passenger_service_application.pdf','transport_information_guide.pdf')");
$findDoc=$conn->prepare('SELECT id FROM documents WHERE file_name=? LIMIT 1');
$insertDoc=$conn->prepare('INSERT INTO documents(title_en,title_si,file_name,file_path) VALUES(?,?,?,?)');
foreach($docs as $doc){
 $path='documents/'.$doc[2];
 if(!is_file(__DIR__.'/'.$path)) continue;
 $findDoc->bind_param('s',$doc[2]);$findDoc->execute();$found=$findDoc->get_result()->fetch_assoc();
 if($found){$updateDoc=$conn->prepare('UPDATE documents SET title_en=?,title_si=?,file_path=?,active=1 WHERE id=?');$updateDoc->bind_param('sssi',$doc[0],$doc[1],$path,$found['id']);$updateDoc->execute();}
 else{$insertDoc->bind_param('ssss',$doc[0],$doc[1],$doc[2],$path);$insertDoc->execute();}
}
?>