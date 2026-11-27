(() => {
	'use strict';

	const table = document.querySelector('.token-records-table');
	if (!table) return;

	const body = table.tBodies[0];
	const rows = Array.from(body.rows);
	const search = document.getElementById('token-table-search');
	const pageSizeSelect = document.getElementById('token-table-page-size');
	const emptyState = document.getElementById('token-table-empty');
	const previousButton = document.getElementById('token-table-previous');
	const nextButton = document.getElementById('token-table-next');
	const rangeStart = document.getElementById('token-table-range-start');
	const rangeEnd = document.getElementById('token-table-range-end');
	const resultTotal = document.getElementById('token-table-total');
	const currentPageLabel = document.getElementById('token-table-current-page');
	const totalPagesLabel = document.getElementById('token-table-total-pages');

	let currentPage = 1;
	let sortedColumn = -1;
	let sortDirection = 1;
	const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

	function readableText(element) {
		const copy = element.cloneNode(true);
		copy.querySelectorAll('button, form, input, select, textarea').forEach((control) => control.remove());
		return copy.innerText.trim();
	}

	function visibleRows() {
		const query = search.value.trim().toLocaleLowerCase();
		if (!query) return rows;
		return rows.filter((row) => {
			const draftComments = Array.from(row.querySelectorAll('textarea')).map((field) => field.value).join(' ');
			return `${readableText(row)} ${draftComments}`.toLocaleLowerCase().includes(query);
		});
	}

	function render() {
		const matches = visibleRows();
		const pageSize = Number.parseInt(pageSizeSelect.value, 10);
		const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
		currentPage = Math.min(currentPage, totalPages);
		const startIndex = (currentPage - 1) * pageSize;
		const pageRows = new Set(matches.slice(startIndex, startIndex + pageSize));

		rows.forEach((row) => {
			row.hidden = !pageRows.has(row);
		});

		emptyState.hidden = matches.length > 0;
		rangeStart.textContent = matches.length ? String(startIndex + 1) : '0';
		rangeEnd.textContent = String(Math.min(startIndex + pageSize, matches.length));
		resultTotal.textContent = String(matches.length);
		currentPageLabel.textContent = String(currentPage);
		totalPagesLabel.textContent = String(totalPages);
		previousButton.disabled = currentPage <= 1;
		nextButton.disabled = currentPage >= totalPages;
	}

	search.addEventListener('input', () => {
		currentPage = 1;
		render();
	});

	pageSizeSelect.addEventListener('change', () => {
		currentPage = 1;
		render();
	});

	previousButton.addEventListener('click', () => {
		if (currentPage > 1) {
			currentPage -= 1;
			render();
		}
	});

	nextButton.addEventListener('click', () => {
		const totalPages = Math.max(1, Math.ceil(visibleRows().length / Number.parseInt(pageSizeSelect.value, 10)));
		if (currentPage < totalPages) {
			currentPage += 1;
			render();
		}
	});

	table.querySelectorAll('[data-sort-column]').forEach((button) => {
		button.addEventListener('click', () => {
			const column = Number.parseInt(button.dataset.sortColumn, 10);
			sortDirection = sortedColumn === column ? -sortDirection : 1;
			sortedColumn = column;

			rows.sort((left, right) => {
				const leftValue = readableText(left.cells[column]);
				const rightValue = readableText(right.cells[column]);
				return collator.compare(leftValue, rightValue) * sortDirection;
			});
			rows.forEach((row) => body.appendChild(row));

			table.querySelectorAll('th[aria-sort]').forEach((heading) => {
				heading.setAttribute('aria-sort', 'none');
			});
			button.closest('th').setAttribute('aria-sort', sortDirection === 1 ? 'ascending' : 'descending');
			currentPage = 1;
			render();
		});
	});

	render();
})();
