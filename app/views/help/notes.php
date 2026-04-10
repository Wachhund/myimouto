<div class="help">
  <h1>Help: Notes</h1>

  <div class="section">
    <p>You can annotate images with notes. This is primarily used to translate text. Please do not use a note when a comment would suffice.</p>
    <p>Notes implement Markdown markup syntax. Read below for more information.</p>
    <?php /*
    <p>Because this feature makes heavy usage of DHTML and Ajax, it probably won't work on many browsers. Currently it's been tested with Firefox 2, IE6, and IE7.</p>
    */ ?>
    <p>If you have an issue with an existing note or have a comment about it, instead of replacing the note, post a comment. Comments are more visible to other users, and chances are someone will respond to your inquiry.</p>
    <h4>How to interact with notes</h4>
    <p>To create a note, with the Shift key pressed, click and drag over the area of the image you want to place it. The text box to edit the text of the note will appear automatically, so you can start typing right away. To save the note you can press Ctrl + Enter, or press the Escape key to cancel the edition.</p>
    <p>You can drag the notes inside the image, and you can resize them by dragging the little black box on the bottom-right corner of the note.</p>
    <p>When you mouse over the note box, the note body will appear. You can click on the body and another box will appear where you can edit the text. This box will also contain four links:</p>
    <ul>
      <li><strong>Save (or Ctrl + Enter)</strong> This saves the note to the database.</li>
      <li><strong>Cancel (or Escape)</strong> This reverts the note to the last saved copy. The note position, dimensions, and text will all be restored.</li>
      <li><strong>History</strong> This will redirect you to the history of the note. Whenever you save a note the old data isn't destroyed. You can always revert to an older version. You can even undelete a note.</li>
      <li><strong>Remove</strong> This doesn't actually remove the note from the database; it only hides it from view. You can undelete a note by reverting to a previous version.</li>
    </ul>
    <?php /*
    <p>All HTML code will be sanitized. You can place small translation notes by surrounding a block of text with <code>&lt;tn&gt;...&lt;/tn&gt;</code> tags.</p>
    */ ?>
    <h4 id="markdown">Markdown for notes</h4>
    <p>In case you need to, you can give the notes some formatting by using <?= $this->linkTo("Markdown's simple syntax", 'http://michelf.ca/projects/php-markdown/concepts/') ?>.</p>
    <p>HTML isn't allowed. However, you can place small translation notes by surrounding a block of text with <code>&lt;tn&gt;...&lt;/tn&gt;</code> tags, which must be alone in a paragraph.</p>
  </div>
</div>

<?php $this->contentFor("subnavbar", function () { ?>
  <li><?= $this->linkTo("Help", "#index") ?></li>
<?php }) ?>