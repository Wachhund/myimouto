<?php $this->provide('title', $this->report_title) ?>
<h4><?= $this->t('report') . $this->h($this->report_title) ?></h4>

<div>
  <div style="margin-bottom: 1em;">
    <?= $this->formTag('report#' . $this->report_action, ['method' => 'get'], function(){ ?>
      <table width="100%">
        <tfoot>
          <tr>
            <td colspan="2"><?= $this->submitTag($this->t('report_search')) ?></td>
          </tr>
        </tfoot>
        <tbody>
          <tr>
            <th width="15%"><label for="start_date"><?= $this->t('report_start') ?></label></th>
            <td width="85%"><?= $this->textFieldTag('start_date', $this->start_date, ['size' => 10]) ?></td>
          </tr>
          <tr>
            <th><label for="end_date"><?= $this->t('report_end') ?></label></th>
            <td><?= $this->textFieldTag('end_date', $this->end_date, ['size' => 10]) ?></td>
          </tr>
          <tr>
            <th><label for="limit"><?= $this->t('report_limit') ?></label></th>
            <td><?= $this->textFieldTag('limit', $this->limit, ['size' => 5]) ?></td>
          </tr>
          <tr>
            <th><label for="level"><?= $this->t('report_level') ?></label></th>
            <td><?= $this->selectTag('level', [$this->level_options, $this->level]) ?></td>
          </tr>
        </tbody>
      </table>
    <?php }) ?>
  </div>

  <div>
    <table width="100%" class="highlightable">
      <thead>
        <tr>
          <th width="15%"><?= $this->t('report_user') ?></th>
          <th width="10%"><?= $this->t('report_changes') ?></th>
          <th width="75%"><?= $this->t('report_percent') ?></th>
        </tr>
      </thead>
      <tfoot>
        <?php $sum = !empty($this->users) ? (int)$this->users[0]['sum'] : 0 ?>
        <tr>
          <td><?= $this->t('report_total') ?></td>
          <td><?= $sum ?></td>
          <td></td>
        </tr>
      </tfoot>
      <tbody>
        <?php foreach ($this->users as $user) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <?php if (!empty($user['id'])) : ?>
              <td><?= $this->linkTo($user['name'], ['user#show', 'id' => $user['id']]) ?></td>
              <td>
                <?php $change_path = call_user_func($this->change_params, $user['id']) ?>
                <?= $this->linkTo((int)$user['change_count'], $change_path) ?>
              </td>
            <?php else : ?>
              <td><?= $this->h($user['name']) ?></td>
              <td><?= (int)$user['change_count'] ?></td>
            <?php endif ?>
            <?php $percent = $sum > 0 ? number_format((100 * (int)$user['change_count']) / $sum, 1) : '0.0' ?>
            <td><?= $percent ?>%</td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?php $common_params = ['start_date' => $this->start_date, 'end_date' => $this->end_date, 'limit' => $this->limit] ?>
<?php if ($this->level !== null) $common_params['level'] = $this->level ?>
<?php $this->contentFor('subnavbar', function() use ($common_params) { ?>
  <li><?= $this->linkTo($this->t('report_tags'), array_merge(['report#tag_updates'], $common_params)) ?></li>
  <li><?= $this->linkTo($this->t('report_notes'), array_merge(['report#note_updates'], $common_params)) ?></li>
  <li><?= $this->linkTo($this->t('report_wiki'), array_merge(['report#wiki_updates'], $common_params)) ?></li>
  <li><?= $this->linkTo($this->t('report_uploads'), array_merge(['report#post_uploads'], $common_params)) ?></li>
  <li><?= $this->linkTo($this->t('report_votes2'), array_merge(['report#votes'], $common_params)) ?></li>
<?php }) ?>
