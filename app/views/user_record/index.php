<div id="user-record">
  <h4><?= $this->t('record_record') ?></h4>

  <form method="get" action="<?= $this->urlFor(['action' => 'index']) ?>" style="margin-bottom: 1em;">
    <?php if ($this->user) : ?>
      <input type="hidden" name="user_id" value="<?= $this->user->id ?>" />
    <?php endif ?>
    <label for="category"><?= $this->t('record_category') ?? 'Category' ?>:</label>
    <select name="category" id="category">
      <option value=""><?= $this->t('record_all') ?? 'All' ?></option>
      <option value="positive" <?= $this->params()->category === 'positive' ? 'selected' : '' ?>>Positive</option>
      <option value="negative" <?= $this->params()->category === 'negative' ? 'selected' : '' ?>>Negative</option>
      <option value="neutral" <?= $this->params()->category === 'neutral' ? 'selected' : '' ?>>Neutral</option>
    </select>
    <input type="submit" value="<?= $this->t('record_filter') ?? 'Filter' ?>" />
  </form>

  <table width="100%" class="highlightable" id="history">
    <thead>
      <tr>
        <th></th>
        <th><?=$this->t('record_user') ?></th>
        <th><?=$this->t('record_reporter') ?></th>
        <th><?= $this->t('record_category') ?? 'Category' ?></th>
        <th><?=$this->t('record_when') ?></th>
        <th><?=$this->t('record_body') ?></th>
        <th><?=$this->t('record_action') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->user_records as $rec) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>" id="record-<?= $rec->id ?>">
          <td style="background: <?= $rec->category === 'positive' ? '#3465a4' : ($rec->category === 'negative' ? '#cc0000' : '#888888') ?>;"><td>
            <?php if ($this->user) : ?>
              <?= $this->linkTo($this->h($rec->user->pretty_name()), ['controller' => "user", 'action' => "show", 'id' => $rec->user_id]) ?>
            <?php else: ?>
              <?= $this->linkTo($this->h($rec->user->pretty_name()), ['action' => "index", 'user_id' => $rec->user_id]) ?>
            <?php endif ?>
          </td>
          <td><?= $this->h($rec->reporter->pretty_name()) ?></td>
          <td><?= $this->h($rec->category) ?></td>
          <td><?= $this->t(['time.x_ago', 't' => $this->timeAgoInWords($rec->created_at)]) ?></td>
          <td class="change"><?= $this->format_text($rec->body) ?></td>
          <td>
            <?php if (current_user()->is_admin() || current_user()->id == $rec->reported_by) : ?>
              <?php if (!$rec->is_deleted) : ?>
                <?= $this->linkToFunction('Archive', "UserRecord.destroy({$rec->id})") ?>
              <?php else : ?>
                <em>Archived</em>
              <?php endif ?>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <div id="paginator">
    <?= $this->willPaginate($this->user_records) ?>
  </div>

  <?= $this->partial("footer", ['user' => $this->user]) ?>
</div>
