// --- Global Data Store ---
let assignments = [];

// --- Element Selections ---
const assignmentForm   = document.getElementById('assignment-form');
const assignmentsTbody = document.getElementById('assignments-tbody');

// --- Functions ---

function createAssignmentRow(assignment) {
    const tr = document.createElement('tr');

    const tdTitle = document.createElement('td');
    tdTitle.textContent = assignment.title;

    const tdDueDate = document.createElement('td');
    tdDueDate.textContent = assignment.due_date;

    const tdDescription = document.createElement('td');
    tdDescription.textContent = assignment.description;

    const tdActions = document.createElement('td');

    const editBtn = document.createElement('button');
    editBtn.className   = 'edit-btn';
    editBtn.dataset.id  = assignment.id;
    editBtn.textContent = 'Edit';

    const deleteBtn = document.createElement('button');
    deleteBtn.className   = 'delete-btn';
    deleteBtn.dataset.id  = assignment.id;
    deleteBtn.textContent = 'Delete';

    tdActions.appendChild(editBtn);
    tdActions.appendChild(deleteBtn);

    tr.appendChild(tdTitle);
    tr.appendChild(tdDueDate);
    tr.appendChild(tdDescription);
    tr.appendChild(tdActions);

    return tr;
}

function renderTable() {
    assignmentsTbody.innerHTML = '';
    assignments.forEach(assignment => {
        const row = createAssignmentRow(assignment);
        assignmentsTbody.appendChild(row);
    });
}

async function handleAddAssignment(event) {
    event.preventDefault();

    const title       = document.getElementById('assignment-title').value;
    const due_date    = document.getElementById('assignment-due-date').value;
    const description = document.getElementById('assignment-description').value;
    const filesRaw    = document.getElementById('assignment-files').value;
    const files       = filesRaw.split('\n').map(f => f.trim()).filter(f => f !== '');

    const submitBtn = document.getElementById('add-assignment');
    const editId    = submitBtn.dataset.editId;

    if (editId) {
        await handleUpdateAssignment(Number(editId), { title, due_date, description, files });
    } else {
        const response = await fetch('./api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, due_date, description, files })
        });

        const result = await response.json();

        if (result.success === true) {
            assignments.push({ id: result.id, title, due_date, description, files });
            renderTable();
            assignmentForm.reset();
        }
    }
}

async function handleUpdateAssignment(id, fields) {
    const response = await fetch('./api/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, ...fields })
    });

    const result = await response.json();

    if (result.success === true) {
        assignments = assignments.map(a =>
            a.id === id ? { id, ...fields } : a
        );
        renderTable();
        assignmentForm.reset();

        const submitBtn = document.getElementById('add-assignment');
        submitBtn.textContent = 'Add Assignment';
        delete submitBtn.dataset.editId;
    }
}

async function handleTableClick(event) {
    const id = Number(event.target.dataset.id);

    if (event.target.classList.contains('delete-btn')) {
        const response = await fetch(`./api/index.php?id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (result.success === true) {
            assignments = assignments.filter(a => a.id !== id);
            renderTable();
        }
    }

    if (event.target.classList.contains('edit-btn')) {
        const assignment = assignments.find(a => a.id === id);

        document.getElementById('assignment-title').value       = assignment.title;
        document.getElementById('assignment-due-date').value    = assignment.due_date;
        document.getElementById('assignment-description').value = assignment.description;
        document.getElementById('assignment-files').value       = assignment.files.join('\n');

        const submitBtn = document.getElementById('add-assignment');
        submitBtn.textContent    = 'Update Assignment';
        submitBtn.dataset.editId = assignment.id;
    }
}

async function loadAndInitialize() {
    const response = await fetch('./api/index.php');
    const result   = await response.json();

    assignments = result.data || [];
    renderTable();

    assignmentForm.addEventListener('submit', handleAddAssignment);
    assignmentsTbody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();
