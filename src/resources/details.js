let currentResourceId = null;
let currentComments = [];

const titleElement = document.querySelector("#resource-title");
const descriptionElement = document.querySelector("#resource-description");
const linkElement = document.querySelector("#resource-link");
const commentList = document.querySelector("#comment-list");
const commentForm = document.querySelector("#comment-form");
const newComment = document.querySelector("#new-comment");

function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderResourceDetails(resource) {
  titleElement.textContent = resource.title;
  descriptionElement.textContent = resource.description || "";
  linkElement.href = resource.link;
}

function createCommentArticle(comment) {
  const article = document.createElement("article");

  const paragraph = document.createElement("p");
  paragraph.textContent = comment.text;

  const footer = document.createElement("footer");
  footer.textContent = `Posted by: ${comment.author}`;

  article.appendChild(paragraph);
  article.appendChild(footer);

  return article;
}

function renderComments() {
  commentList.innerHTML = "";

  currentComments.forEach((comment) => {
    commentList.appendChild(createCommentArticle(comment));
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newComment.value.trim();

  if (commentText === "") {
    return;
  }

  const response = await fetch("./api/index.php?action=comment", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      resource_id: currentResourceId,
      author: "Student",
      text: commentText,
    }),
  });

  const result = await response.json();

  if (result.success) {
    currentComments.push(result.data);
    renderComments();
    newComment.value = "";
  }
}

async function initializePage() {
  currentResourceId = getResourceIdFromURL();

  if (!currentResourceId) {
    titleElement.textContent = "Resource not found.";
    return;
  }

  const [resourceResponse, commentsResponse] = await Promise.all([
    fetch(`./api/index.php?id=${currentResourceId}`),
    fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`),
  ]);

  const resourceResult = await resourceResponse.json();
  const commentsResult = await commentsResponse.json();

  if (resourceResult.success && resourceResult.data) {
    renderResourceDetails(resourceResult.data);

    currentComments =
      commentsResult.success && commentsResult.data ? commentsResult.data : [];

    renderComments();
    commentForm.addEventListener("submit", handleAddComment);
  } else {
    titleElement.textContent = "Resource not found.";
  }
}

initializePage();