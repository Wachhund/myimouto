<?php
$app_name = htmlspecialchars((string) CONFIG()->app_name, ENT_QUOTES, 'UTF-8');
$admin_contact = htmlspecialchars((string) CONFIG()->admin_contact, ENT_QUOTES, 'UTF-8');
$template = htmlspecialchars((string) CONFIG()->url_base . '/post/index?tags={searchTerms}', ENT_QUOTES, 'UTF-8');
?>
<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  <ShortName><?= $app_name ?></ShortName>
  <Description><?= 'Search images in ' . $app_name . ' image board.' ?></Description>
  <Contact><?= $admin_contact ?></Contact>
  <AdultContent>1</AdultContent>
  <Url type="text/html" template="<?= $template ?>" />
</OpenSearchDescription>
