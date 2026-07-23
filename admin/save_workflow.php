<?php

require_once '../includes/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SESSION['role'] != 'admin') {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized."
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);
    exit();
}

$category_id = intval($data['category_id']);
$workflow = $data['workflow'];

if ($category_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Please select a category."
    ]);
    exit();
}

if (count($workflow) == 0) {
    echo json_encode([
        "success" => false,
        "message" => "Workflow cannot be empty."
    ]);
    exit();
}

mysqli_begin_transaction($conn);

try{

    /* Remove existing workflow */

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM approval_workflow
         WHERE category_id=?"
    );

    mysqli_stmt_bind_param($stmt,"i",$category_id);
    mysqli_stmt_execute($stmt);

    /* Insert new workflow */

    $order = 1;

    foreach($workflow as $step){

        $position_id = intval($step['position_id']);

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO approval_workflow
            (category_id,position_id,approval_order)
            VALUES(?,?,?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iii",
            $category_id,
            $position_id,
            $order
        );

        mysqli_stmt_execute($stmt);

        $order++;

    }

    mysqli_commit($conn);

    echo json_encode([
        "success"=>true,
        "message"=>"Workflow saved successfully."
    ]);

}catch(Exception $e){

    mysqli_rollback($conn);

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}