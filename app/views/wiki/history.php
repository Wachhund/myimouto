<?= $this->partial("sidebar") ?>

<div class="content">
  <?= $this->formTag(['action' => "diff"], ['method' => 'get'], function(){ ?>
    <?= $this->hiddenFieldTag("title", $this->params()->title) ?>

    <table id="history" width="100%">
      <thead>
        <tr>
          <th>From</th>
          <th>To</th>
          <th>Date</th>
          <th>User</th>
          <th>Changes</th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <td colspan="2"><?= $this->submitTag("Compare") ?></td>
        </tr>
      </tfoot>
      <tbody>
        <?php foreach ($this->wiki_pages as $i => $wiki_page) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td><?= $this->radioButtonTag("from", $wiki_page->version, $i == 1, ['id' => "from_".$wiki_page->version]) ?></td>
            <td><?= $this->radioButtonTag("to", $wiki_page->version, $i == 0, ['id' => "to_".$wiki_page->version]) ?></td>
            <td><?= $this->linkTo(date("Y-m-d H:i", strtotime($wiki_page->updated_at)), ['action' => "show", 'title' => $wiki_page->title, 'version' => $wiki_page->version]) ?></td>
            <td><?= $this->linkTo($this->h($wiki_page->author()), ['controller' => 'user', 'action' => 'show', 'id' => $wiki_page->user_id]) ?></td>
            <td class="change"><?= $this->page_change($wiki_page, $this->wiki_pages[$i + 1]) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php }) ?>

  <script type="text/javascript">
    var from;
    var to;

    function validateFrom(self)
    {
      if(Number(self.value) >= to) return false;
      from = new Number(self.value);
      return true;
    }

    function validateTo(self)
    {
      if(Number(self.value) <= from) return false;
      to = new Number(self.value);
      return true;
    }

    for(var i=1, elem; i <= <?= $this->wiki_pages->size() ?>; i++)
    {
      elem = $("from_"+i);
      elem.onclick = function() {return validateFrom(this);};
      if(elem.checked) from = i;

      elem = $("to_"+i);
      elem.onclick = function() {return validateTo(this);};
      if(elem.checked) to = i;
    }
  </script>
</div>


<?= $this->partial("footer") ?>
