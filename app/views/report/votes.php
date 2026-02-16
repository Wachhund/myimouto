<?php $this->provide('title', $this->t('report_votes')) ?>
<h4><?= $this->t('report_votes') ?></h4>

<div>
  <p><?= $this->t('report_votes_text') ?></p>

  <div style="float: left; width: 260px;">
    <table width="100%">
      <thead>
        <tr>
          <th><?= $this->t('report_user') ?></th>
          <th><?= $this->t('report_total') ?></th>
          <th><?= $this->t('report_votes2') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->users as $user) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td>
              <?php if (!empty($user['id'])) : ?>
                <?= $this->linkTo($user['name'], ['user#show', 'id' => $user['id']]) ?>
              <?php else : ?>
                <?= $this->h($user['name']) ?>
              <?php endif ?>
            </td>
            <td>
              <?php if (!empty($user['id'])) : ?>
                <?= $this->linkTo((int)$user['change_count'], ['post#index', 'tags' => 'vote:>=1:' . $user['name'] . ' order:vote']) ?>
              <?php else : ?>
                <?= (int)$user['change_count'] ?>
              <?php endif ?>
            </td>
            <td>
              <span class="stars" style="white-space: nowrap">
                <?php foreach (range(1, 3) as $vote) : ?>
                  <?php $count = isset($user['votes'][$vote]) ? (int)$user['votes'][$vote] : 0 ?>
                  <?php if (!empty($user['id'])) : ?>
                    <a class="star star-<?= $vote ?>" href="<?= $this->urlFor(['post#index', 'tags' => 'vote:>=' . $vote . ':' . $user['name'] . ' order:vote']) ?>">
                      <?= $count ?> <span class="score-on score-voted score-visible">&#9733;</span>
                    </a>
                  <?php else : ?>
                    <span class="star star-<?= $vote ?>">
                      <?= $count ?> <span class="score-on score-voted score-visible">&#9733;</span>
                    </span>
                  <?php endif ?>
                <?php endforeach ?>
              </span>
            </td>
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
