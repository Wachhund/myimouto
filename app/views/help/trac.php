<div class="help">
  <h1>Help: Trac</h1>
  
  <p>The best way to submit new bugs and feature requests is to create a ticket in <a href="http://trac.donmai.us">Trac</a>. Simply click the <em>New Ticket</em> button on the Trac site and enter a short title and description. For bug reports, try and enter some steps that reproduce the bug.</p>
</div>

<?php $this->contentFor("subnavbar", function () { ?>
  <li><?= $this->linkTo("Help", "#index") ?></li>
<?php }) ?>