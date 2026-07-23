<?php

require_once __DIR__ . '/../includes/session.php';

if($_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

/* Categories */

$categories = mysqli_query($conn,"
SELECT
category_id,
category_name
FROM resource_categories
ORDER BY category_name
");

/* Positions */

$positions = mysqli_query($conn,"
SELECT
position_id,
position_name
FROM positions
ORDER BY position_name
");

?>

<div class="main">

<h1>Workflow Builder</h1>

<div class="builder-container">

<div class="builder-card">

<h2>Select Resource Category</h2>

<select id="categorySelect">

<option value="">Choose Category</option>

<?php while($cat=mysqli_fetch_assoc($categories)){ ?>

<option value="<?= $cat['category_id']; ?>">

<?= htmlspecialchars($cat['category_name']); ?>

</option>

<?php } ?>

</select>

</div>

<div class="builder-card">

<h2>Available Positions</h2>

<div id="availablePositions">

<?php while($pos=mysqli_fetch_assoc($positions)){ ?>

<div
class="position-item"
data-id="<?= $pos['position_id']; ?>">

<?= htmlspecialchars($pos['position_name']); ?>

<button
type="button"
class="add-btn"
onclick="addPosition(<?= $pos['position_id']; ?>, '<?= htmlspecialchars($pos['position_name'], ENT_QUOTES); ?>')">

+

</button>

</div>

<?php } ?>

</div>

</div>

<div class="builder-card">

<h2>Approval Workflow</h2>

<div id="workflowList">

<p class="empty">

No approval stages added.

</p>

</div>

</div>

<button
type="button"
class="btn"
id="saveWorkflow">

Save Workflow

</button>

</div>

</div>

<script>

let workflow=[];

const categorySelect=document.getElementById("categorySelect");

categorySelect.addEventListener("change",loadWorkflow);

function loadWorkflow(){

    workflow=[];

    const category=categorySelect.value;

    if(category===""){

        renderWorkflow();

        return;

    }

    fetch("load_workflow.php?category_id="+category)

    .then(response=>response.json())

    .then(data=>{

        workflow=data.map(step=>({
            position_id: step.position_id,
            position_name: step.position_name
        }));

        renderWorkflow();

    });

}

function renderWorkflow(){

    /* Render workflow */

    let html="";

    if(workflow.length===0){

        html="<p class='empty'>No approval stages added.</p>";

    }else{

        workflow.forEach((step,index)=>{

            html+=`
            <div class="workflow-stage">

                <strong>${index+1}. ${step.position_name}</strong>

                <div>

                    <button onclick="moveUp(${index})">↑</button>

                    <button onclick="moveDown(${index})">↓</button>

                    <button onclick="removeStage(${index})">🗑</button>

                </div>

            </div>
            `;

        });

    }

    document.getElementById("workflowList").innerHTML=html;

    /* Disable already-added positions */

    document.querySelectorAll(".position-item").forEach(item=>{

        const id=parseInt(item.dataset.id);

        const btn=item.querySelector(".add-btn");

        if(workflow.some(step=>step.position_id==id)){

            btn.disabled=true;
            btn.innerHTML="✓";

        }else{

            btn.disabled=false;
            btn.innerHTML="+";

        }

    });

}

function addPosition(id,name){

    if(workflow.some(step=>step.position_id==id)){

        alert("Position already added.");

        return;

    }

    workflow.push({

        position_id:id,

        position_name:name

    });

    renderWorkflow();

}

function removeStage(index){

    workflow.splice(index,1);

    renderWorkflow();

}

function moveUp(index){

    if(index===0)return;

    [workflow[index],workflow[index-1]]=[workflow[index-1],workflow[index]];

    renderWorkflow();

}

function moveDown(index){

    if(index===workflow.length-1)return;

    [workflow[index],workflow[index+1]]=[workflow[index+1],workflow[index]];

    renderWorkflow();

}

document
.getElementById("saveWorkflow")
.addEventListener("click", saveWorkflow);

function saveWorkflow(){

    const category = categorySelect.value;

    if(category===""){

        alert("Select a category.");

        return;

    }

    if(workflow.length===0){

        alert("Workflow is empty.");

        return;

    }

    fetch("save_workflow.php",{

        method:"POST",

        headers:{
            "Content-Type":"application/json"
        },

        body:JSON.stringify({

            category_id:category,

            workflow:workflow

        })

    })

    .then(response=>response.json())

    .then(data=>{

        alert(data.message);

    });

}

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>