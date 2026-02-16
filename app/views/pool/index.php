<div id="pool-index">
  <div style="margin-bottom: 2em;">
    <?= $this->formTag([], ['method' => 'get'], function(){ ?>
      <?php if ($this->params()->order) : ?>
      <?= $this->hiddenFieldTag("order", $this->params()->order) ?>
      <?php endif ?>
      <?= $this->textFieldTag("query", $this->h($this->params()->query), ['size' => 40]) ?>
      <?= $this->submitTag($this->t('.search'), ['name' => '']) ?>
    <?php }) ?>
  </div>

  <?= $this->imageTag('images/blank.gif', ['id' => 'hover-thumb', 'alt' => '', 'style' => 'position: absolute; display: none; border: 2px solid #000; right: 42%;']) ?>

  <table width="100%" class="highlightable">
    <thead>
      <tr>
        <th width="60%"><?=$this->t('.table.name') ?></th>
        <th width="*"><?=$this->t('.table.creator') ?></th>
        <th width="*"><?=$this->t('.table.posts') ?></th>
        <th width="*"><?=$this->t('.table.created') ?></th>
        <th width="*"><?=$this->t('.table.updated') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->pools as $p) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>" id="p<?= $p->id ?>">
          <td><?= $this->linkTo($this->h($p->pretty_name()), ['action' => "show", 'id' => $p->id]) ?></td>
          <td><?= $this->h($p->user->pretty_name()) ?></td>
          <td><?= $p->post_count ?></td>
          <td><?= $this->t(['time.x_ago', 't' => $this->timeAgoInWords($p->created_at)]) ?></td>
          <td><?= $this->t(['time.x_ago', 't' => $this->timeAgoInWords($p->updated_at)]) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<div id="paginator">
  <?= $this->willPaginate($this->pools) ?>
</div>

<?php $this->contentFor('post_cookie_javascripts', function(){ ?>
<script type="text/javascript">
  var thumb = $("hover-thumb");
  <?php foreach ($this->samples as $pool_id => $post) : ?>
    Post.register(<?= $post->toJson() ?>);
    var hover_row = $("p<?= $pool_id ?>");
    var container = hover_row.up("TABLE");
    Post.init_hover_thumb(hover_row, <?= $post->id ?>, thumb, container);
  <?php endforeach ?>
  Post.init_blacklisted({replace: true});

  <?php foreach ($this->samples as $post) : ?>
    if(!Post.is_blacklisted(<?= $post->id ?>))
      Preload.preload('<?= $post->preview_url() ?>');
  <?php endforeach ?>
</script>
<?php }) ?>

<?= $this->partial("footer") ?>
