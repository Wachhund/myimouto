<div class="help">
  <h1>Help: Wiki</h1>
  <p>Danbooru uses <?= $this->linkTo("DText", "#dtext") ?> for all formatting.</p>
  <p>To create an internal wiki link, wrap the title in two sets of square brackets. <code>[[Like this]]</code>.</p>
  
  <div class="section">
    <h4>Search</h4>
    <p>By default when you search for a keyword Danbooru will search both the title and the body. If you want to only search the title, prefix your query with title. For example: "title:tag group".</p>
  </div>
  
  <div class="section">
    <h4>Style Guideline</h4>
    <p>The Danbooru wiki has no specific style guide, but here's some general advice for creating wiki pages for tags:</p>
    <ul>
      <li>Use h4 for all headers, h6 for subheaders.</li>
      <li>Bundle any relevant links at the end under a <strong>See also</strong> section.</li>
      <li>For artists, include the artist's home page under the <strong>See also</strong> section</li>
    </ul>
  </div>
</div>

<?php $this->contentFor("subnavbar", function () { ?>
  <li><?= $this->linkTo("Help", "#index") ?></li>
<?php }) ?>
