<thead>
  <tr>
    <th></th>
    <th width="15%"><?=$this->t('sub_name') ?></th>
    <th width="70%"><?=$this->t('sub_tags') ?></th>
    <th width="10%"><?=$this->t('sub_vis') ?></th>
  </tr>
</thead>

<tfoot>
  <tr>
    <td></td>
    <td colspan="3">
      <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
      <?= $this->submitTag($this->t('sub_save')) ?>
      <input onclick="new Ajax.Request('/tag_subscription/create.js', {asynchronous:true, evalScripts:true, parameters:'csrf_token=<?= rawurlencode($this->csrf_token) ?>'});" type="button" value="<?= $this->t('sub_add') ?>">
    </td>
  </tr>
</tfoot>

<tbody id="tag-subscription-body">
  <?php foreach ($this->tag_subscriptions as $tag_subscription) : ?>
    <?= $this->partial("listing_row", ['tag_subscription' => $tag_subscription, 'csrf_token' => $this->csrf_token]) ?>
  <?php endforeach ?>
</tbody>
