<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/shared.php');
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../odst_parser.php');
// In case short tag is enabled
echo '<?xml version="1.0" encoding="UTF-8" ?>';?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>Halo SP - Update metadata</title>
  <link rel="stylesheet" href="demo_css.css" type="text/css" />
</head>
<body>
  <div class="header">
    <h1>Halo SP</h1>
    <p>Demonstation pages to show off the features of Halo Stats Processor</p>
  </div>
  
  <div id="data_menu">
    <h2>Update stored ODST metadata</h2>
      <p>
        <a href="index.html" title="Main Page">Return to main page</a>
      </p>
  </div>
  
  <p>Processing...<?php
$metadata = new ODSTMetadata;
$metadata->get_metadata();
$metadata->load_metadata();
file_put_contents(METADATA_FILE, serialize($metadata));
?> Done.</p>

  <p>
    Local metadata updated, you can
    <a href="index.html" title="Link back to the main screen">go back to the main menu</a>.
  </p>

</body>
</html>