
async function loadTable() {
    const params = `action=getTableData&table=${currentTable}&page=${currentPage}&limit=10`;

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

    for (let i = 0; i < columns.length; i++) {
        html += `<th>${columns[i]}</th>`;
    }
    html += '<th>Действия</th></thead><tbody>';
    for (let i = 0; i < data.length; i++) {
        const row = data[i];
        const firstCol = columns[0];
        const id = row[firstCol];
        html += '<tr>';
        for (let j = 0; j < columns.length; j++) {
            const col = columns[j];
            html += `<td>${row[col] ?? ''}</td>`;
        }
        html += `<td>
            <button class="edit-btn" data-id="${id}">edit</button>
            <button class="delete-btn" data-id="${id}">delete</button>
        </td>`;
        html += '</tr>';
    }
    html += '</tbody></table>';

    const limit = 10;
    const totalPages = Math.ceil(total / limit);
    if (totalPages > 1) {
        html += '<div class="pagination">';
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage)
                html += `<button disabled>${i}</button>`;
            else
                html += `<button class="page-btn" data-page="${i}">${i}</button>`;
        }
        html += '</div>';
    }

    html += '<br><div><button id="addRecordBtn" class="add-btn">+ Добавить запись</button></div>';
    document.getElementById('content').innerHTML = html;

    const pageBtns = document.querySelectorAll('.page-btn');
    for (let i = 0; i < pageBtns.length; i++) {
        pageBtns[i].addEventListener('click', function() {
            currentPage = parseInt(this.dataset.page);
            loadTable();
        });
    }
    const editBtns = document.querySelectorAll('.edit-btn');
    for (let i = 0; i < editBtns.length; i++) {
        editBtns[i].addEventListener('click', function() {
            const id = this.dataset.id;
            openEditForm(id);
        });
    }
    const deleteBtns = document.querySelectorAll('.delete-btn');
    for (let i = 0; i < deleteBtns.length; i++) {
        deleteBtns[i].addEventListener('click', function() {
            const id = this.dataset.id;
            deleteRecord(id);
        });
    }
    const addBtn = document.getElementById('addRecordBtn');
    if (addBtn) addBtn.addEventListener('click', function() { openAddForm(); });
}

async function saveRecord(data, id = null) {
    const params = (id == null)
        ? `action=insertRecord&table=${currentTable}`
        : `action=updateRecord&table=${currentTable}&id=${id}`;

    const response = await fetch(`api.php?${params}`, {
        method: 'POST',
        body: JSON.stringify(data)
    });
    const result = await response.json();

    if (result.success) {
        modal.style.display = 'none';
        loadTable();
    } else {
        alert(`Ошибка: ${result.message}`);
    }
}

async function getTableColumns() {
    const params = `action=getTableData&table=${currentTable}&page=1&limit=1`;
    const response = await fetch(`api.php?${params}`);
    const result = await response.json();
    if (result.success && result.data.length) {
        return Object.keys(result.data[0]);
    } else {
        return [];
    }
}

function getPrimaryKeyName() {
    const map = {
        'Movies': 'movie_id',
        'Halls': 'hall_id',
        'Seats': 'seat_id',
        'Clients': 'client_id',
        'Sessions': 'session_id',
        'Tickets': 'ticket_id'
    };
    return map[currentTable] || 'id';
}

async function openAddForm() {
    const columns = await getTableColumns();
    let html = `<h2>Добавить запись в таблицу ${currentTable}</h2><form id="recordForm">`;
    const pkName = getPrimaryKeyName();

    for (let i = 0; i < columns.length; i++) {
        const col = columns[i];
        if (col === pkName)
            continue;
        html += `<label>${col}:</label><br>
            <input type="text" name="${col}" placeholder="Введите знаечние">
        <br>`;
    }

    html += '<br><button type="submit">Сохранить</button></form>';
    modalBody.innerHTML = html;
    modal.style.display = 'block';
    document.getElementById('recordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const obj = {};

        for (let [key, val] of formData.entries())
            obj[key] = val;
        saveRecord(obj);
    });
}

async function openEditForm(id) {
    const params = `action=getRecord&table=${currentTable}&id=${id}`;
    const response = await fetch(`api.php?${params}`);
    const result = await response.json();
    if (!result.success) {
        alert(`Ошибка загрузки записи: ${result.message}`);
        return;
    }
    const data = result.data;
    const columns = Object.keys(data);
    let html = `<h2>Редактировать запись в ${currentTable}</h2><form id="recordForm">`;
    const pkName = getPrimaryKeyName();

    for (let i = 0; i < columns.length; i++) {
        const col = columns[i];
        const val = data[col] ?? '';
        if (col === pkName) {
            html += `<label>${col}:</label><br>
                ${val}
            <br>`;
        } else {
            html += `<label>${col}:</label><br>
                <input type="text" name="${col}" value="${val}">
            <br>`;
        }
    }

    html += '<br><button type="submit">Сохранить</button></form>';
    modalBody.innerHTML = html;
    modal.style.display = 'block';
    document.getElementById('recordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const obj = {};

        for (let [key, val] of formData.entries())
            obj[key] = val;
        saveRecord(obj, id);
    });
}

async function deleteRecord(id) {
    if (!confirm('Удалить запись?'))
        return;

    const params = `action=deleteRecord&table=${currentTable}&id=${id}`;

    const response = await fetch(`api.php?${params}`);
    const result = await response.json();

    if (result.success) {
        loadTable();
    } else {
        alert(`Ошибка: ${result.message}`);
    }
}

function openAddHallForm() {
    let html = `
        <h2>Добавить зал с местами</h2>
        <form id="hallForm">
            <label>Номер зала (hall_number):</label><br>
            <input type="text" name="hall_number" required><br>
            <label>Тип зала (hall_type):</label><br>
            <select name="hall_type">
                <option value="Standard">Standard</option>
                <option value="VIP">VIP</option>
                <option value="IMAX">IMAX</option>
            </select><br>
            <label>Описание:</label><br>
            <textarea name="description"></textarea><br>
            <label>Количество рядов (rows):</label><br>
            <input type="number" name="rows" min="1" required><br>
            <label>Мест в ряду (seats_per_row):</label><br>
            <input type="number" name="seats_per_row" min="1" required><br>
            <br>
            <button type="submit">Создать зал и места</button>
        </form>
    `;
    modalBody.innerHTML = html;
    modal.style.display = 'block';

    document.getElementById('hallForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const obj = {};
        for (let [key, val] of formData.entries())
            obj[key] = val;

        // Преобразуем числа
        obj.rows = parseInt(obj.rows);
        obj.seats_per_row = parseInt(obj.seats_per_row);

        const response = await fetch('api.php?action=addHallWithSeats', {
            method: 'POST',
            body: JSON.stringify(obj)
        });
        const result = await response.json();

        if (result.success) {
            modal.style.display = 'none';
            // Если текущая таблица Halls, перезагружаем её
            if (currentTable === 'Halls') {
                loadTable();
            } else {
                alert('Зал и места созданы. Перейдите на таблицу "Залы", чтобы увидеть.');
            }
        } else {
            alert('Ошибка: ' + result.message);
        }
    });
}

let currentTable = 'Movies';
let currentPage = 1;

const modal = document.getElementById('modal');
const modalBody = document.getElementById('modal-body');
const closeModal = document.querySelector('.close');
const addHallBtn = document.getElementById('btnAddHallSeats');

closeModal.addEventListener('click', function() {
    modal.style.display = 'none';
});

addHallBtn.addEventListener('click',function() {
    openAddHallForm();
});

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