const resourceListSection = document.querySelector(
  "#resource-list-section"
);

function createResourceArticle(resource) {
  const article = document.createElement("article");

  article.innerHTML = `
    <h2>${resource.title}</h2>
    <p>${resource.description || ""}</p>

    <a href="${resource.link}" target="_blank">
        ${resource.link}
    </a>

    <br><br>

    <a href="details.html?id=${resource.id}">
        View Resource & Discussion
    </a>
`;

  return article;
}

async function loadResources() {
  const response = await fetch("./api/index.php");
  const result = await response.json();

  resourceListSection.innerHTML = "";

  if (result.success && result.data) {
    result.data.forEach((resource) => {
      const article = createResourceArticle(resource);
      resourceListSection.appendChild(article);
    });
  }
}

loadResources();
