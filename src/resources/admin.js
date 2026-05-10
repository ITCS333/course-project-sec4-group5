let resources = [];
let editId = null;

const resourceForm = document.querySelector("#resource-form");
const resourcesTbody = document.querySelector("#resources-tbody");
const submitButton = document.querySelector("#add-resource");

function createResourceRow(resource) {
    const tr = document.createElement("tr");

    tr.innerHTML = `
        <td>${resource.title}</td>

        <td>${resource.description || ""}</td>

        <td>
           
    <a href="${resource.link}" target="_blank">${resource.link}</a>
</td>
        

        <td>
            <button class="edit-btn" data-id="${resource.id}">
                Edit
            </button>

            <button class="delete-btn" data-id="${resource.id}">
                Delete
            </button>
        </td>
    `;

    return tr;
}

function renderTable(resourceList) {
  resourcesTbody.innerHTML = "";

  resourceList.forEach((resource) => {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}


async function handleAddResource(event) {
  event.preventDefault();

  const title = document.querySelector("#resource-title").value;
  const description = document.querySelector("#resource-description").value;
  const link = document.querySelector("#resource-link").value;

  if (editId) {
    const response = await fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: editId, title, description, link }),
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.map((resource) =>
        resource.id == editId
          ? { ...resource, title, description, link }
          : resource
      );

      editId = null;
      submitButton.textContent = "Add Resource";
      resourceForm.reset();
     renderTable(resources);
    }

    return;
  }

  const response = await fetch("./api/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ title, description, link }),
  });

  const result = await response.json();

  if (result.success) {
    resources.push({
      id: result.id,
      title,
      description,
      link,
    });

    resourceForm.reset();
    renderTable(resources);
  }
}

async function handleTableClick(event) {
  const clickedButton = event.target;

  if (clickedButton.classList.contains("delete-btn")) {
    const id = clickedButton.dataset.id;

    const response = await fetch(`./api/index.php?id=${id}`, {
      method: "DELETE",
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.filter((resource) => resource.id != id);
      renderTable(resources);
    }
  }

  if (clickedButton.classList.contains("edit-btn")) {
    const id = clickedButton.dataset.id;
    const resource = resources.find((resource) => resource.id == id);

    if (resource) {
      document.querySelector("#resource-title").value = resource.title;
      document.querySelector("#resource-description").value =
        resource.description || "";
      document.querySelector("#resource-link").value = resource.link;

      editId = id;
      submitButton.textContent = "Update Resource";
    }
  }
}

async function loadAndInitialize() {
  const response = await fetch("./api/index.php");
  const result = await response.json();

  if (result.success) {
    resources = result.data;
    renderTable(resources);
  }

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize(); 