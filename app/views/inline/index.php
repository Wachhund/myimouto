<div id="pool-index">
  <table width="100%" class="highlightable">
    <thead>
      <tr>
        <th width="40px"><?= $this->t('.first_image') ?></th>
        <th width="60%"><?= $this->t('.description') ?></th>
        <th width="*"><?= $this->t('.user') ?></th>
        <th width="*"><?= $this->t('.images') ?></th>
        <th width="*"><?= $this->t('.created') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->inlines as $p) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>" id="p<?= $p->id ?>">
          <td>
            <a href='<?= $this->urlFor(['#edit', 'id' => $p->id]) ?>'>
            <?php if ($p->inline_images->any()) : ?>
              <?= $this->imageTag($p->inline_images[0]->preview_url(), ['alt' => "thumb", 'width' => $p->inline_images[0]->preview_dimensions()['width'], 'height' => $p->inline_images[0]->preview_dimensions()['height'], 'loading' => 'lazy']) ?>
            <?php else: ?>
              (no images)
            <?php endif ?>
            </a>
          </td>
          <td><?= $this->h($p->description) ?></td>
          <td><?= $this->linkTo($this->h($p->user->pretty_name()), ["user#show", 'id' => $p->user->id]) ?></td>
          <td><?= $p->inline_images->size() ?></td>
          <td><?= $this->t('time.x_ago', ['t' => $this->t($this->timeAgoInWords($p->created_at))]) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<div id="paginator">
  <?= $this->willPaginate($this->inlines) ?>
</div>

<?= $this->formTag(["#create"], ['id' => "create-new"], function () { ?>
<?php }) ?>

<?php $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('.create'), "#", ['level' => 'member', 'onclick' => "$('create-new').submit(); return false;"]) ?></li>
<?php }) ?>

<?= $this->partial("footer") ?>
