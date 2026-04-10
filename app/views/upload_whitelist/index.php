<?php $this->provide('title', 'Upload Whitelist') ?>

<h4>Upload Whitelist</h4>

<div style="margin-bottom: 1em;">
  <?= $this->formTag(['upload_whitelist#is_allowed'], ['method' => 'get'], function () { ?>
    <label for="url">Check URL:</label>
    <?= $this->textFieldTag("url", $this->h($this->params()->url), ['size' => 60]) ?>
    <?= $this->submitTag("Check") ?>
  <?php }) ?>
</div>

<div style="margin-bottom: 1em;">
  <?= $this->formTag([], ['method' => 'get'], function () { ?>
    <?= $this->textFieldTag("query", $this->h($this->params()->query), ['size' => 40, 'placeholder' => 'Search patterns...']) ?>
    <?= $this->submitTag("Search") ?>
  <?php }) ?>
</div>

<table width="100%" class="highlightable">
  <thead>
    <tr>
      <th width="5%">#</th>
      <th width="25%">Pattern</th>
      <th width="8%">Allowed</th>
      <th width="20%">Reason</th>
      <th width="20%">Note (staff)</th>
      <th width="8%">Hidden</th>
      <th width="14%">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->rules as $rule) : ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
        <td><?= $rule->id ?></td>
        <td><?= $this->h($rule->pattern) ?></td>
        <td><?= $rule->allowed ? '<span style="color:green">Yes</span>' : '<span style="color:red">No</span>' ?></td>
        <td><?= $this->h($rule->reason) ?></td>
        <td><?= $this->h($rule->note) ?></td>
        <td><?= $rule->hidden ? 'Yes' : 'No' ?></td>
        <td>
          <?= $this->formTag(['upload_whitelist#destroy', 'id' => $rule->id], ['method' => 'post', 'style' => 'display:inline'], function () { ?>
            <?= $this->submitTag('Delete', ['onclick' => "return confirm('Delete this rule?')"]) ?>
          <?php }) ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->rules) ?>
</div>

<div style="margin-top: 2em; border-top: 1px solid #ccc; padding-top: 1em;">
  <h5>Add Rule</h5>
  <?= $this->formTag(['upload_whitelist#create'], ['method' => 'post'], function () { ?>
    <table>
      <tr>
        <th><label for="upload_whitelist_pattern">Pattern</label></th>
        <td><?= $this->textFieldTag('upload_whitelist[pattern]', '', ['size' => 40, 'placeholder' => '*.example.com']) ?></td>
      </tr>
      <tr>
        <th><label for="upload_whitelist_allowed">Allowed</label></th>
        <td>
          <select name="upload_whitelist[allowed]">
            <option value="1" selected>Yes (allow)</option>
            <option value="0">No (deny)</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="upload_whitelist_reason">Reason</label></th>
        <td><?= $this->textFieldTag('upload_whitelist[reason]', '', ['size' => 40]) ?></td>
      </tr>
      <tr>
        <th><label for="upload_whitelist_note">Note (staff)</label></th>
        <td><?= $this->textFieldTag('upload_whitelist[note]', '', ['size' => 40]) ?></td>
      </tr>
      <tr>
        <th><label for="upload_whitelist_hidden">Hidden</label></th>
        <td>
          <select name="upload_whitelist[hidden]">
            <option value="0" selected>No</option>
            <option value="1">Yes</option>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag('Create Rule') ?></td>
      </tr>
    </table>
  <?php }) ?>
</div>
