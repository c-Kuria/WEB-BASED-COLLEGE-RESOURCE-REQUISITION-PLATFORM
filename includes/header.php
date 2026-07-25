<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage =
    basename($_SERVER['PHP_SELF']);

$currentRole =
    $_SESSION['role'] ?? '';

$currentUsername =
    $_SESSION['username'] ?? 'User';

$pageTitle =
    $pageTitle ?? 'Resource Requisition System';

/*
|--------------------------------------------------------------------------
| Base URL
|--------------------------------------------------------------------------
|
| Change this only if your project folder has a different name.
|
*/

$baseUrl =
    '/resource_requisition';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="College Resource Requisition Management System"
    >

    <title>
        <?= htmlspecialchars($pageTitle); ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= $baseUrl; ?>/assets/css/style.css"
    >

</head>

<body>

<div class="app-shell">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="app-body">

        <header class="top-header">

            <div class="top-header-left">

                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobileMenuButton"
                    aria-label="Open navigation"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="top-header-brand">

                    <h1>
                        Resource Requisition
                    </h1>

                    <p>
                        College Resource Management System
                    </p>

                </div>

            </div>

            <div class="top-header-user">

                <div class="top-header-user-text">

                    <strong>
                        <?= htmlspecialchars(
                            $currentUsername
                        ); ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars(
                            ucfirst($currentRole)
                        ); ?>
                    </span>

                </div>

                <a
                    href="<?= $baseUrl; ?>/<?= htmlspecialchars(
                        $currentRole
                    ); ?>/profile.php"
                    class="header-avatar"
                    title="View profile"
                >
                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $currentUsername,
                                0,
                                1
                            )
                        )
                    ); ?>
                </a>

            </div>

        </header>

        <main class="page-content">