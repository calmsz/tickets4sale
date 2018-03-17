<?php
require 'vendor/autoload.php';
use Carbon\Carbon;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <script
        src="https://code.jquery.com/jquery-1.12.4.js"
        crossorigin="anonymous"></script>

    <script
        src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"
        crossorigin="anonymous"></script>

    <title>Ticket Form</title>
</head>
<body>
    <form method="post">
        <label for="showDate">Show Date (yyyy-mm-dd):</label>
        <input type="text" name="showDate" id="showDate">

        <input type="submit" value="Submit">
    </form>
    
    <?php
        if (count($_POST) > 0) {
            include 'showInventory.php';            
            
            
            $inventory = new showInventory;
            $inventory->load(['', '../data/shows.csv', '2018-06-02', $_POST['showDate']])
                ->getShowsAtDate()
                ->parseOutputObject()
                ->printJsonInventory();
        }
    ?>


</body>

<script language="javascript">
<?php
echo "var dataSet = new Array(".$inventory->getArrayGenres().");";
 ?>
console.log(dataSet);

</script>

</html>
