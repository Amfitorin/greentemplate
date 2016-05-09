<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="<?php echo $direction; ?>" lang="<?php echo $language; ?>" xml:lang="<?php echo $language; ?>">
<head>
<title><?php echo $title; ?></title>
<base href="<?php echo $base; ?>" />
</head>
<body style="margin: 0; padding: 20px 10px;">
<div style="text-align: center;">
  <p style="font: bold 14px arial, verdana, tahoma, sans-serif; color: #444;"><?php echo $responser; ?></p>
</div>
<script type="text/javascript"><!--
setTimeout('location = \'<?php echo $continue; ?>\';', 2500);
//--></script>
</body>
</html>