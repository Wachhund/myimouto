<?= $this->formFor($this->ad, function ($f) { ?>
  <?php $action = $this->request()->action() ?>
  <?= $this->partial('shared/error_messages', ['object' => $f->object()]) ?>

  <table class="form">
    <tbody>
      <tr>
        <th width="150px;">Type</th>
        
        <td>
          <label for="type-image">Image</label>
          <input type="radio" name="type" id="type-image" checked />
          
          <label for="type-html">Html</label>
          <input type="radio" name="type" id="type-html" />
          
          <script>
          jQuery(function(){
            var $ = jQuery;
            $('#type-image').on('click', function() {
              if ($(this).is(':checked')) {
                $('.type-fields').hide();
                $('.type-image').show();
              }
            });
            $('#type-html').on('click', function() {
              if ($(this).is(':checked')) {
                $('.type-fields').hide();
                $('.type-html').show();
              }
            });
          });
          </script>
        </td>
      </tr>
    </tbody>
    
    <tbody>
      <tr>
        <th><?= $f->label('ad_type') ?></th>
        <td><?= $f->select('ad_type', ['Horizontal' => 'horizontal', 'Vertical' => 'vertical']) ?></td>
      </tr>
      <script>
      jQuery(function(){
        var $ = jQuery;
        var p = $('tbody.ad_position');
        $('#advertisement_ad_type').on('change', function(){
          if ($(this).val() == 'horizontal') {
            p.show();
          } else {
            p.hide();
          }
        });
      });
      </script>
    </tbody>
   
    <tbody class="ad_position<?php if ($f->object()->ad_type == 'vertical') {
        echo ' hide-box';
    } ?>">
      <tr>
        <th><?= $f->label('position') ?></th>
        <td><?= $f->select('position', ['Any' => 'a', 'Top' => 't', 'Bottom' => 'b']) ?></td>
      </tr>
    </tbody>
    
    <tbody>
      <tr>
        <th><?= $f->label('status') ?></th>
        <td><?= $f->select('status', ['Active' => 'active', 'Disabled' => 'disabled']) ?></td>
      </tr>
      
      <?php if (false && $action == 'edit') : // Why is this here??> 
      <tr>
        <th><?= $f->label('reset_hit_count') ?></th>
        <td><?= $this->checkBoxTag('reset_hit_count', 1, false, ['id' => 'advertisement_reset_hit_count']) ?></td>
      </tr>
      <?php endif ?> 
      
      <tr>
        <th><?= $f->label('width') ?></th>
        <td><?= $f->field('number', 'width', ['value' => $f->object()->width ?: 0, 'min' => 0]) ?></td>
      </tr>
      <tr>
        <th><?= $f->label('height') ?></th>
        <td><?= $f->field('number', 'height', ['value' => $f->object()->height ?: 0, 'min' => 0]) ?></td>
      </tr>
    </tbody>

    <tbody class="type-fields type-image<?php if ($action != 'blank' && $f->object()->html) {
        echo ' hide-box';
    } ?>">
      <tr>
        <th><?= $f->label('image_url') ?></th>
        <td><?= $f->textField('image_url') ?></td>
      </tr>
      <tr>
        <th><?= $f->label('referral_url') ?></th>
        <td><?= $f->textField('referral_url') ?></td>
      </tr>
    </tbody>
    
    <tbody class="type-fields type-html<?php if ($action == 'blank' || !$f->object()->html) {
        echo ' hide-box';
    } ?>">
      <tr>
        <th><?= $f->label('html') ?></th>
        <td><?= $f->textArea('html', ['style' => 'height: 300px;']) ?></td>
      </tr>
    </tbody>
    
    <tbody>
      <tr>
        <td colspan="2"><?= $f->submit() ?></td>
      </tr>
    </tbody>
  </table>
<?php }) ?>

<style>
table.form [type=text], table.form textarea{
  width: 100%;
  box-sizing: border-box;
}
.hide-box {
  display:none;
}
</style>
