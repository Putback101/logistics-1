<?php
require_once __DIR__ . "/../../config/app.php";
$base = app_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="<?= $base ?>/assets/logo.png">
  <title>Logistics 1</title>

  <!-- UI fonts & icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <!-- Keep Bootstrap available for existing components -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- ByaHERO UI CSS stack -->
  <link rel="stylesheet" href="<?= $base ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/layout.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/components.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/modules/logistics.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/responsive.css">
</head>
<body class="hr-ui">

