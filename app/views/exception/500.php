<?= '<?xml version="1.0" encoding="utf-8"?>' ?> 
<!DOCTYPE html PUBLIC "-//W3C/DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>Error</title>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon"/>
  <?= $this->stylesheetLinkTag("application", ['media' => 'screen', 'title' => 'default']) ?> 
</head>
<body>
  <div style="text-align: center;">
    <img src="/images/errors.png" alt="errors" style="margin: auto;" />
    <p style="width: 30em; margin: auto;">An error has occurred.</p>
    <?php if (!empty($this->error_code)) : ?>
      <p style="width: 30em; margin: auto; margin-top: 1em; font-size: 0.85em; color: #666;">Error code: <?= htmlspecialchars($this->error_code) ?></p>
    <?php endif ?>
  </div>
</body>
</html>
