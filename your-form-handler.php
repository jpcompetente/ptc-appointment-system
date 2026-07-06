<!DOCTYPE html>
<html>
<head>
    <title>Thank You!</title>
    <link rel="stylesheet" type="text/css" href="css/ty.css">
</head>
<body>
    <div class="container">
        <h2>Thank You!</h2>
        <?php
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            
            $name = htmlspecialchars($_POST['name']);

            
            echo "<p>Dear $name,</p>";
            echo "<p>Your appointment has been taken!</p>";
            echo "<p>Plese be patient,<br>we will get back to you soon.</p>";
        } else {
            
            echo "<p>Sorry, you are not allowed to access this page directly.</p>";
        }
        ?>
    </div>
</body>
</html>

