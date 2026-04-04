let currentAssignmentId = null;
let currentComments     = [];

function getAssignmentIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

function renderAssignmentDetails(assignment) {
    document.getElementById('assignment-title').textContent       = assignment.title;
    document.getElementById('assignment-due-date').textContent    = 'Due: ' + assignment.due_date;
    document.getElementById('assignment-description').textContent = assignment.description;

    const filesList = document.getElementById('assignment-files-list');
    filesList.innerHTML = '';
  (assignment.files || []).forEach(url => {
        const li = document.createElement('li');
        const a  = document.createElement('a');
        a.href        = url;
        a.textContent = url;
        li.appendChild(a);
        filesList.appendChild(li);
    });
}

function createCommentArticle(comment) {
    const article = document.createElement('article');

    const p = document.createElement('p');
    p.textContent = comment.text;

    const footer = document.createElement('footer');
    footer.textContent = 'Posted by: ' + comment.author;

    article.appendChild(p);
    article.appendChild(footer);

    return article;
}

function renderComments() {
    const commentList = document.getElementById('comment-list');
    commentList.innerHTML = '';
    currentComments.forEach(comment => {
        commentList.appendChild(createCommentArticle(comment));
    });
}

async function handleAddComment(event) {
    event.preventDefault();

    const newCommentInput = document.getElementById('new-comment');
    const commentText = newCommentInput.value.trim();
    if (!commentText) return;

    const response = await fetch('./api/index.php?action=comment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            assignment_id: currentAssignmentId,
            author:        'Student',
            text:          commentText
        })
    });

    const result = await response.json();

    if (result.success === true) {
        currentComments.push(result.data);
        renderComments();
        newCommentInput.value = '';
    }
}

async function initializePage() {
    currentAssignmentId = getAssignmentIdFromURL();

    const assignmentTitle = document.getElementById('assignment-title');

    if (!currentAssignmentId) {
        assignmentTitle.textContent = 'Assignment not found.';
        return;
    }

    const [assignmentRes, commentsRes] = await Promise.all([
        fetch(`./api/index.php?id=${currentAssignmentId}`),
        fetch(`./api/index.php?action=comments&assignment_id=${currentAssignmentId}`)
    ]);

    const assignmentJson = await assignmentRes.json();
    const commentsJson   = await commentsRes.json();

    currentComments = commentsJson.data || [];

    if (assignmentJson.success && assignmentJson.data) {
        renderAssignmentDetails(assignmentJson.data);
        renderComments();
        document.getElementById('comment-form').addEventListener('submit', handleAddComment);
    } else {
        assignmentTitle.textContent = 'Assignment not found.';
    }
}

if (typeof module === 'undefined') {
    initializePage();
}
