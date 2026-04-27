
let currentTable = 'Movies';
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-table]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentTable = btn.dataset.table;
            currentPage = 1;
            loadTable();
        });
    });
    loadTable();
});

async function loadTable() {
    const params = `action=getTableData&table=${currentTable}&page=${currentPage}&limit=10`

    try {
        const response = await fetch(`api.php?${params}`);

        if (response.ok != true) {
            document.getElementById('content').innerHTML = `<div class="error">
                Ошибка: статус сообщения ${response.status} - ${response.statusText}
            </div>`;
        }
        else {
            const result = await response.json();

            if (result.success) {
                renderTable(result.data, result.total);
            } else {
                document.getElementById('content').innerHTML = `<div class="error">
                    Ошибка: ${result.message}
                </div>`;
            }
        }
    } catch (e) {
        document.getElementById('content').innerHTML = `<div class="error">
            Ошибка загрузки: ${e.message}
        </div>`;
    }
}

function renderTable(data, total) {
    if (!data.length) {
        document.getElementById('content').innerHTML = '<p>Нет данных</p>';
        return;
    }

    const columns = Object.keys(data[0]);
    let html = '<table border="1"><thead><tr>';

    columns.forEach(function(col) {
        html += `<th>${col}</th>`
    });
    html += '<th>Действия</th></thead><tbody>';
    data.forEach(function(row) {
        html += '<tr>';
        columns.forEach(function(col) {
            html += `<td>${row[col] ?? ''}</td>`
        });
        html += `<td>
            <button class="edit-btn" data-id="${row[columns[0]]}">edit</button>
            <button class="delete-btn" data-id="${row[columns[0]]}">delete</button>
        </td>`;
        html += '</tr>';
    });
    html += '</tbody></table>';

    const limit = 10;
    const totalPages = Math.ceil(total / limit);
    if (totalPages > 1) {
        html += '<div class="pagination">';
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="page-btn" data-page="${i}" ${i === currentPage ? 'disabled' : ''}>${i}</button>`;
        }
        html += '</div>';
    }
    document.getElementById('content').innerHTML = html;
    
    document.querySelectorAll('.page-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentPage = parseInt(btn.dataset.page);
            loadTable();
        });
    });
}