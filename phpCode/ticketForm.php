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
        
    <title>Ticket Form</title>
    <style>
.intro th { 
    background-color: lightgray;
    padding: 2px;
    text-align: left;
    width: 100%;
}
th, td {
    border-bottom: 1px solid #ddd;
}
    </style>
</head>
<body>
    <?php
        if (isset($_POST['showDate'])) {
            include 'showInventory.php';            
            $postDate = $_POST['showDate'];
            $queryDate = Carbon::now('Europe/London')->format('Y-m-d');
            $inventory = new showInventory;
            $inventory->load(['', '../data/shows.csv', $queryDate, $postDate])
                ->getShowsAtDate()
                ->parseOutputObject();
        echo "Show date: $postDate. ";
        echo "Query date: $queryDate";
        } ?>
    <form method="post">
        <label for="showDate">Show Date (yyyy-mm-dd):</label>
        <input type="text" name="showDate" id="showDate">

        <input type="submit" value="Submit">
    </form>

    <div id="genres">

    </div>

</body>

<script language="javascript">

<?php
    echo "const dataSet = new Array(".$inventory->getArrayGenres().");";
 ?>
 
let domParent = $("#genres");
let genres = dataSet[0];

genres.forEach(element => {
    let shows = element.shows;
    domParent.html(domParent.html() + `<div id='${element.genre}'>`);
    domParent.html(domParent.html() + `<H3>${element.genre}</H3>`);
        shows.forEach(showie => {
            domParent.html(domParent.html() + `
        <table class='intro'>
        <tr>
        <th>Title</th>
        <th>Tickets Left</th>
        <th>Tickets Available</th>
        <th>Status</th>
        <th>Price</th>
        </tr>
        <tr>
        <td>${showie.title}</td>
        <td>${showie['tickets left']}</td>
        <td>${showie['tickets available']}</td>
        <td>${showie['status']}</td>
        <td>${showie['price']}</td>
        </tr>
        </table>`);    
        });  
    domParent.html(domParent.html() + `</div>`);
});
</script>

</html>
