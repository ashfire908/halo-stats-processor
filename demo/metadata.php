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
    <p>Demonstration pages to show off the features of Halo Stats Processor</p>
  </div>
  
  <div id="data_menu">
    <h2>Update stored ODST metadata</h2>
      <p>
        <a href="index.html" title="Main Page">Return to main page</a>
      </p>
  </div>
  
  <p>Processing...<?php
// Check if the metadata file exists but is not writeable
if (file_exists(METADATA_FILE) and ! is_writable(METADATA_FILE)) {
  ?> Error.</p>
  
  <p>
    Metadata file is not writable. Update failed.<br />
    <a href="index.html" title="Main menu">Return to the main menu</a>.
  </p><?php
} else {
  if (!$metadata_file = @fopen(METADATA_FILE, 'wb')) {
    ?> Error.</p>
  
  <p>
    Can't open metadata file. Update failed.<br />
    <a href="index.html" title="Main menu">Return to the main menu</a>.
  </p><?php
  } else {
    $metadata = new ODSTMetadata;
    $metadata->get_metadata();
    $metadata->load_metadata();
    if (fwrite($metadata_file, serialize($metadata)) === FALSE) {
       ?> Error.</p>
  
  <p>
    Can't write to metadata file. Update failed.<br />
    <a href="index.html" title="Main menu">Return to the main menu</a>.
  </p><?php
    } else {
      ?> Done.</p>

  <p>Local metadata updated. <a href="index.html" title="Main menu">Return to the main menu</a>.</p><?php
    }
  }
}
?>

</body>
</html>
