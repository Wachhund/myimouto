<div class="overlay-notice-container" id="notice-container" style="display: none;">
  <table cellspacing="0" cellpadding="0"> <tbody>
    <tr> <td>
      <div id="notice">
      </div>
    </td> </tr>
  </tbody> </table>
</div>
<?php $this->contentFor('post_cookie_javascripts', function () { ?>
  <script type="text/javascript">
    var text = Cookie.get("notice");
    if (text) {
      notice(text, true);
      Cookie.remove("notice");
    }
  </script>
<?php }) ?>
