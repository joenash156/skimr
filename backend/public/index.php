
<?php
  $projectName = "Skimr";
  $message = "Welcome to your new PHP project!";
  $currentDate = date("l, F j, Y");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $projectName; ?></title>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      background: #f4f4f9;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #222;
    }

    .container {
      background: white;
      padding: 40px;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
      max-width: 450px;
      width: 90%;
    }

    h1 {
      color: #6c45d9;
      margin-bottom: 15px;
    }

    p {
      margin-bottom: 15px;
      line-height: 1.6;
    }

    .status {
      display: inline-block;
      background: #e4f8eb;
      color: #218443;
      padding: 8px 15px;
      border-radius: 20px;
      font-size: 14px;
      margin-top: 10px;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1><?php echo $projectName; ?></h1>

    <p><?php echo $message; ?></p>

    <p>Today is <?php echo $currentDate; ?></p>

    <div class="status">
      PHP is working successfully!
    </div>
  </div>
</body>
</html>
