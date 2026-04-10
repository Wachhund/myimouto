<div id="artist-index">
  <div id="search-form" style="margin-bottom: 1em;">
    <?= $this->formTag([], ['method' => 'get'], function () { ?>
      <?= $this->textFieldTag('name', $this->params()->name, ['size' => 40]) ?> <?= $this->submitTag($this->t('.search')) ?>
      <br />
      <?= $this->selectTag('order', [[$this->t('.name') => 'name', $this->t('.date') => 'date'], ($this->params()->order ?: '')]) ?>
    <?php }) ?>
  </div>

  <?php if (!$this->artists->blank()) : ?>
    <table class="highlightable" width="100%">
      <thead>
        <tr>
          <th width="5%"></th>
          <th width="30%"><?= $this->t('.name') ?></th>
          <th width="35%"><?= 'Other names' // $this->t('.aliases')?></th>
          <th width="20%"><?= $this->t('.updated_by') ?></th>
          <th width="10%"><?= $this->t('.last_modified') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->artists as $artist) : ?>
          <tr class=<?= $this->cycle('even', 'odd') ?> id="artist-<?= $artist->id ?>">
            <td>
              <?= $this->linkToIf(!$artist->alias_id, 'P', ['controller' => 'post', 'action' => 'index', 'tags' => $artist->name], ['title' => $this->t('.find')]) ?>
              <?= $this->linkTo('E', ['action' => 'update', 'id' => $artist->id], ['title' => $this->t('.edit')]) ?>
              <?= $this->linkTo('D', ['action' => 'destroy', 'id' => $artist->id], ['title' => $this->t('.delete')]) ?>
            </td>
            <td>
              <?= $this->linkTo($artist->name, ['action' => 'show', 'id' => $artist->id]) ?>
              <?php if ($artist->alias_id) : ?>
                &rarr; <?= $this->linkTo($artist->alias_name(), ['action' => 'show', 'id' => $artist->alias_id], ['title' => $this->t('.is_alias')]) ?>
              <?php endif ?>
              <?php if ($artist->group_id) : ?>
                [<?= $this->linkTo($artist->group_name(), ['action' => 'show', 'id' => $artist->group_id], ['title' => $this->t('.is_group')]) ?>]
              <?php endif ?>
            </td>
            <td><?= implode(', ', array_map(function ($x) {
                return $this->linkTo($this->h($x->name), ['#show', 'id' => $x->id]);
            }, $artist->aliases()->members())) ?></td>
            <?php if ($artist->updater_id) : ?>
              <td><?= User::find_name($artist->updater_id) ?></td>
            <?php else: ?>
              <td></td>
            <?php endif ?>
            <td><?= date('M d Y, H:i', strtotime($artist->updated_at)) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>

  <div id="paginator">
    <?= $this->willPaginate($this->artists) ?>
  </div>

  <?= $this->partial("footer") ?>
</div>
