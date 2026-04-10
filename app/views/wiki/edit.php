<?= $this->partial("sidebar") ?>

<div class="content" style="float: left; width: 40em;">
  <h2 class="wiki-title"><?= $this->h($this->wiki_page->pretty_title()) ?> (Editing)</h2>
  <div id="wiki-view">
  </div>
  <?= $this->formTag(['action' => "update"], ['level' => 'member'], function () { ?>
    <?= $this->hiddenField("wiki_page", "title", ['value' => $this->wiki_page->title]) ?>
    <?= $this->partial("edit_buttons") ?>
  <?php }) ?>
</div>

<div style="float: left; margin-left: 5em; width: 15em;">
  <h4>Reference</h4>
  <pre>
A paragraph.

Followed by another.

h4. A header

* List item 1
* List item 2
* List item 3

Linebreaks are important between lists,
headers, and paragraphs.

URLs are automatically linked: http://www.google.com

A [[wiki link]] (underscores are not needed).

A {{post link}}.

<a href="/help/dtext">Read more</a>.
  </pre>
</div>

<?= $this->partial("footer") ?>
