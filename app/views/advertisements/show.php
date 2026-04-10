<h4>Advertisement #<?= $this->ad->id ?></h4>

<table>
  <tbody>
    <tr>
      <th><label><?= $this->humanize('id') ?></label></th>
      <td><?= $this->ad->id ?></td>
    </tr>
    <tr>
      <th><label><?= $this->humanize('ad_type') ?></label></th>
      <td><?= $this->ad->ad_type ?></td>
    </tr>
    <?php if ($this->ad->ad_type == 'horizontal') : ?>
    <tr>
      <th><label><?= $this->humanize('position') ?></label></th>
      <td><?= $this->ad->prettyPosition() ?></td>
    </tr>
    <?php endif ?>
    <tr>
      <th><label><?= $this->humanize('status') ?></label></th>
      <td><?= $this->ad->status ?></td>
    </tr>
    <tr>
      <th><label><?= $this->humanize('hit_count') ?></label></th>
      <td><?= $this->ad->hit_count ?></td>
    </tr>
    <tr>
      <th><label><?= $this->humanize('width') ?></label></th>
      <td><?= $this->ad->width ?></td>
    </tr>
    <tr>
      <th><label><?= $this->humanize('height') ?></label></th>
      <td><?= $this->ad->height ?></td>
    </tr>
    <?php if ($this->ad->html) : ?>
    <tr>
      <th><label>Html</label></th>
      <pre style="font-size:1.15em;margin:0px;">
      <td><?= $this->h($this->ad->html) ?></td>
      </pre>
    </tr>
    <?php else: ?>
    <tr>
      <th><label><?= $this->humanize('image_url') ?></label></th>
      <td><?= $this->h($this->ad->image_url) ?></td>
    </tr>
    <tr>
      <th><label><?= $this->humanize('referral_url') ?></label></th>
      <td><?= $this->h($this->ad->referral_url) ?></td>
    </tr>
    <?php endif ?>
  </tbody>
</table>

<?= $this->linkTo($this->t('buttons.edit'), $this->editAdvertisementPath($this->ad)) ?> 
<?= $this->linkTo($this->t('buttons.delete'), $this->ad, ['data' => ['confirm' => $this->t('confirmations.is_sure')], 'method' => 'delete']) ?> 
<?= $this->linkTo($this->t('buttons.back'), $this->advertisementsPath()) ?> 
