<?php include("includes/header.php")?>
	
  <?php include("includes/nav.php") ?>
  <div class="jumbotron">
	  <h1 class="text-center"> Hi there </h1>
  </div>

  <?php
    $sql = "Select * from users";
    $result = query($sql);
    
    confirm($result);

    $row = fetch_array($result);

    echo $row["username"];
  ?>

<?php include("includes/footer.php") ?>


