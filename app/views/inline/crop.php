<div id="set-avatar" class="page">
<?= $this->t('.crop_avatar') ?>
  <p>

  <div class="avatar-crop">
    <?= $this->inline_image_tag($this->image, ['use_sample' => true], ['id' => "image"]) ?>
  </div>

  <?= $this->formTag([], ['id' => "crop", 'level' => 'member'], function () { ?>
    <?= $this->hiddenFieldTag("id", $this->image->id) ?>
    <?= $this->hiddenFieldTag("left", 0) ?>
    <?= $this->hiddenFieldTag("right", 0) ?>
    <?= $this->hiddenFieldTag("top", 0) ?>
    <?= $this->hiddenFieldTag("bottom", 0) ?>
  <?php }) ?>

  <script type="text/javascript" charset="utf-8">
  function onEndCrop(coords, dimensions) {
    $("left").value = (coords.x1 / $("image").width).toFixed(4);
    $("right").value = (coords.x2 / $("image").width).toFixed(4);
    $("top").value = (coords.y1 / $("image").height).toFixed(4);
    $("bottom").value = (coords.y2 / $("image").height).toFixed(4);
  }

  // example with a preview of crop results, must have minimumm dimensions
  var width = $("image").width;
  var height = $("image").height;
  var options =
  {
    displayOnInit: true,
    onEndCrop: onEndCrop,
    minWidth: 1,
    minHeight: 1
  }

  '/* Default to a square selection. */'
  if(width < height)
    options.onloadCoords = { x1: width/4, y1: width/4, x2: width*2/4, y2: width*2/4 }
  else
    options.onloadCoords = { x1: height/4, y1: height/4, x2: height*2/4, y2: height*2/4 }

  new Cropper.ImgWithPreview("image", options)

  OnKey(13, {AlwaysAllowOpera: true}, function(e) {
    $("crop").submit();
    return true;
  });

  </script>
</div>
