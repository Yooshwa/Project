<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sweetkart</title>
</head>
<body>

  <div id="content">
    <?php include('home.php'); ?>
  </div>

<script>
      // fetch and load the content dynamically
    function loadPage(page, link) {
    fetch(page)
        .then(res => res.text())
        .then(data => document.getElementById('content').innerHTML = data)
        .catch(() => document.getElementById('content').innerHTML = "<p>Sorry, page could not be loaded.</p>");

    document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
    link.classList.add('active');
}

  </script>

</body>
</html>
