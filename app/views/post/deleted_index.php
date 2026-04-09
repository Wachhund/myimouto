<h4><?= $this->t('.title') ?></h4>

<table width="100%" class="highlightable">
  <thead>
    <tr>
<!--      <th width="5%">Resolved</th> -->
      <th width="5%"><?= $this->t('.post') ?></th>
      <th width="10%"><?= $this->t('.user') ?></th>
      <th width="45%"><?= $this->t('.tags') ?></th>
      <th width="35%"><?= $this->t('.reason') ?></th>
      <?php if (current_user()->is_mod_or_higher()) : ?>
      <th width="1*"><?= $this->t('.by') ?></th>
      <?php endif ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->posts as $post) : ?>
      <?php $post_flag = $post->latest_flag(); ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
<!--        <td><?= $post_flag && $post_flag->is_resolved ? 'Yes' : 'No' ?></td> -->
        <td><?= $this->linkTo($post->id, ['action' => 'show', 'id' => $post->id]) ?></td>
        <td><?= $this->linkTo($this->h($post->author()), ['user#show', 'id' => $post->user_id]) ?></td>
        <td><?= $this->h($post->cached_tags) ?></td>
        <td><?= $post_flag ? $this->h($post_flag->reason) : '' ?></td>
        <?php if (current_user()->is_mod_or_higher()) : ?>
        <td><?= $post_flag ? $this->linkTo($this->h($post_flag->author()), ['user#show', 'id' => $post_flag->user_id]) : '' ?></td>
        <?php endif ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->posts) ?>
</div>

<?= $this->partial('footer') ?>
