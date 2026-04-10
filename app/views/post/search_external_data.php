<style>
.post_compare {
  border:1px solid gray;
  padding:3px;
}
.post_compare .header {
  background-color:#444;
  padding:3px;
  margin-bottom:10px;
}
.post_compare .header .title {
  color:white;
  font-weight:bold;
  display:block;
  width:100%;
}
.post_compare .header .title:hover {
  color:gray;
}
.comparing {
}
.comparing .preview {
  float:left;
  width:300px;
  height:300px;
  margin-right:10px;
  text-align:center;
}
.clear {
  clear:both;
}
table.form {
  width:65em;
}
table.form th {
  width:150px;
}
table.form td.diff {
  color:rgb(255, 142, 142);
}
table.form td.tags:not(.diff) a {
  color:white;
}
table.form td.tags:not(.diff) a:hover {
  color:#aaa;
}
.none {
  color:gray;
}
</style>
<h1 style="margin-bottom:1.5em;">Search posts data</h1>

<?php
if ($this->posts->any()) :
    foreach ($this->posts as $post) :
        ?>
<div class="post_compare" id="p<?= $post->id ?>">
  <div class="header">
    <h4><a href="#" class="title">Post #<?= $post->id ?></a></h4>
  </div>
  <div>
    <div class="post_ext_data">
      <div class="comparing">
        <div class="preview"><?= $this->linkTo($this->imageTag($post->preview_url(), ['loading' => 'lazy']), ['post#show', 'id' => $post->id], ['target' => '_blank']) ?></div>
        <div><table class="form"><tbody>
          <tr><th>Id</th><td><?= $post->id ?></td></tr>
          <tr><th>Tags</th><td class="post_tags"><?= $post->cached_tags ?></td></tr>
          <tr><th>Source</th><td class="post_source"><?= $post->source ?: '<em class="none">none</em>' ?></td></tr>
          <tr><th>Rating</th><td class="post_rating"><?= $post->pretty_rating() ?></td></tr>
          <tr><th>Width</th><td class="post_width"><?= $post->width ?></td></tr>
          <tr><th>Height</th><td class="post_height"><?= $post->height ?></td></tr>
          <tr><th>File size</th><td class="post_file_size" bytes="<?= $post->file_size ?>"><?= $this->numberToHumanSize($post->file_size) ?></td></tr>
        </tbody></table></div>
        <h4>New data</h4>
        <form class="new_post_data">
          <table class="form"><tfoot>
            <tr>
              <td colspan="2">
                <button class="merge_results_tags" type="button">Merge all tags</button>
              </td>
            </tr>
            <tr>
              <td colspan="2">
                <input type="submit" value="Update" />
              </td>
            </tr>
          </tfoot>
          <tbody>
            <tr>
              <th><label class="block" for="p<?= $post->id ?>_post_rating">Rating</label></th>
              <td>
                <?= $this->radioButtonTag("post[rating]", "e", $post->rating == "e", ['id' =>  'p' . $post->id . '_post_rating_explicit']) ?>
                <label for="p<?= $post->id ?>_post_rating_explicit"><?= $this->t('ratings.e') ?></label>
                <?= $this->radioButtonTag("post[rating]", "q", $post->rating == "q", ['id' =>  'p' . $post->id . '_post_rating_questionable']) ?>
                <label for="p<?= $post->id ?>_post_rating_questionable"><?= $this->t('ratings.q') ?></label>
                <?= $this->radioButtonTag("post[rating]", "s", $post->rating == "s", ['id' =>  'p' . $post->id . '_post_rating_safe']) ?>
                <label for="p<?= $post->id ?>_post_rating_safe"><?= $this->t('ratings.s') ?></label>
              </td>
            </tr>
            <tr>
              <th><label class="block" for="p<?= $post->id ?>_post_source">Source</label></th>
              <td><?= $this->textFieldTag("post[source]", $post->source, ['size' => '40', 'id' => 'p' . $post->id . '_post_source']) ?></td>
            </tr>
            <tr>
              <th width="15%"><label class="block" for="p<?= $post->id ?>_post_tags">Tags</label></th>
              <td width="85%"><?= $this->textAreaTag("post[tags]", $post->cached_tags, ['size' => '50x6', 'id' => 'p' . $post->id . '_post_tags']) ?></td>
            </tr>
          </tbody><table>
        </form>
        <div class="clear"></div>
      </div>
    </div>
    
    <hr />
    <strong>Search results</strong>
    <hr />
    
    <div class="external_data">
      <em><strong>Fetching data...</strong></em>
    </div>
  </div>
</div>
<?php endforeach ?>
<script>
(function($){
var no_data_text = '<em class="none">none</em>'
Post.external_data = function(id) {
  var e_id = '#p' + id,
    e = $(e_id),
    data = {id:id, services:'all', data_search:true}
    console.log(data)
  $.ajax({
    url: '<?= $this->urlFor(['#similar', 'format' => 'json']) ?>',
    data: data,
    dataType: 'json',
    type: 'POST',
    success: function(resp) {
      // console.log(resp)
      if (resp.error) {
        if (typeof resp.error == 'string')
            error = 'Error: ' + resp.error
        else {
          var error = 'Errors:<br/><br/>',
            es = []
          resp.error.forEach(function(e) {
            var msg = ''
            if (e.server)
              msg += 'Server: ' + e.server + '; '
            msg += 'Message: '
            if (e.message)
              msg += e.message
            else
              msg += '[No message]'
            es.push(msg)
          })
          error += es.join('<br />')
        }
        $(e_id + ' .external_data em strong').html(error)
        notice('There was an error with Post #' + id)
        return
      } else if (!resp.posts || !resp.posts.length) {
        $(e_id + ' .external_data em strong').html('No results')
        return
      }
      e.find('.external_data').html('')
      var p_data = e.find('.post_ext_data .form'),
          post_tags = p_data.find('.post_tags').html().split(' ').sort().join(' '),
          props = ['source', 'rating', 'width', 'height', 'file_size']
      resp.posts.forEach(function(p){
        if (p.rating) {
          switch (p.rating) {
            case 's':
              p.rating = 'Safe'
              break;
            case 'q':
              p.rating = 'Questionable'
              break;
            case 'e':
              p.rating = 'Explicit'
              break;
          }
        }
        var preview = $('<div/>').addClass('preview'),
            img = $('<img/>')
        if (p.url)
          preview.append($('<a/>').attr('target', '_blank').attr('href', p.url).append(img))
        else
          preview.append(img)
        if (p.original_preview_url) {
          img.attr('src', p.original_preview_url).error(function(){
            $(this).attr('src', p.preview_url).error(function(){
              $(this).remove()
            })
          })
        } else if (p.preview_url) {
          img.attr('src', p.preview_url).error(function(){
              $(this).remove()
          })
        }
        
        var tbody = $('<tbody/>'),
            tr = $('<tr/>'),
            th = $('<th/>'),
            td = $('<td/>'),
            a  = $('<a/>').attr('href', '#')
        
        tbody.append(tr.clone().append(th.clone().html('Id')).append(td.clone().html(p.id || no_data_text)))
        
        var ext_post_tags = p.tags ? p.tags.split(' ').sort().join(' ') : '',
            tags_td = td.clone().addClass('tags'),
            es = {
              source_td : td.clone(),
              rating_td : td.clone().addClass('post_rating'),
              width_td : td.clone(),
              height_td : td.clone(),
              file_size_td : td.clone()
            }

        if (!ext_post_tags) {
          tags_td.addClass('none').html(no_data_text)
        } else {
          ext_post_tags.split(' ').forEach(function(t){
            tags_td.append(a.clone().html(t)).html(tags_td.html() + ' ')
          })
        }
        
        if (post_tags != ext_post_tags)
          tags_td.addClass('diff')
        
        tbody.append(tr.clone().append(th.clone().html('Tags<p><a href="#" class="add_all_tags">add all</a></p>')).append(tags_td))
        
        props.forEach(function(prop) {
          var res_val = p[prop], local_val = p_data.find('.post_'+prop).html()
          
          if ((prop == 'width' || prop == 'height' || prop == 'file_size') && res_val) {
            var value = ' ' + p[prop], diff = false
            
            if (prop == 'file_size')
              local_val = p_data.find('.post_'+prop).attr('bytes')
            
            if (res_val > local_val)
              diff = parseInt(res_val) - parseInt(local_val)
            else if (res_val < local_val)
              diff = (parseInt(local_val) - parseInt(res_val)) *-1
            
            if (diff !== false) {
              if (diff > 0) {
                diff = diff.toString()
                diff = '+' + (prop == 'file_size' ? number_to_human_size(diff) : diff.toString())
              } else {
                if (prop == 'file_size') {
                  diff = '-' + number_to_human_size(diff *-1).toString()
                }
              }
              if (prop == 'file_size')
                value = number_to_human_size(p[prop])
              
              value += ' (' + diff + ')'
            } else {
              if (prop == 'file_size')
                value = number_to_human_size(p[prop])
            }
          } else if (prop == 'source' && res_val) {
            value = a.clone().addClass('change_post_source').html(res_val)
          } else
            value = res_val || no_data_text
            
          if (value != no_data_text && (res_val != local_val))
            es[prop + '_td'].addClass('diff')
          
          es[prop + '_td'].html(value)
          var pretty_prop = prop.substr(0, 1).toUpperCase() + prop.replace('_', ' ').substr(1)
          tbody.append(tr.clone().append(th.clone().html(pretty_prop)).append(es[prop + '_td']))
        })
        
        if (p.has_png)
          tbody.append(tr.clone().append(th.clone().html('Has PNG')).append(td.clone().html('Yes')))
        tbody.append(tr.clone().append(th.clone().html('Similarity')).append(td.clone().html(p.similarity || no_data_text)))
        
        var table = $('<table/>').addClass('form').append(tbody),
            icon = $('<img/>').attr('src', p.icon_path)
        var data_e = $('<div/>').addClass('post_ext_data').attr('service', p.service).append(
          $('<div/>').addClass('header').append(
            $('<a/>').attr('href', '#').addClass('title').append(icon).append(' ' + p.service)
          )
        ).append(
          $('<div/>').addClass('service comparing').append(preview).append(table)
        ).append($('<div/>').addClass('clear'))
        
        e.find('.external_data').append(data_e)
      })
    },
    error: function(resp) {
      console.log(resp)
      $(e_id + ' .external_data em strong').html('An error occured')
      notice('There was an error with Post #' + id)
    }
  })
}

function post_container(id) {
  if (typeof id == 'number')
    id = id.toString()
  return $('#p' + id)
}

function merge_tags(id) {
  var e = post_container(id),
      all_tags = get_tags_for(id).split(' ')
  e.find('.external_data').find('.post_ext_data').each(function(){
    var t = $(this).find('.tags')
    if (!t.is(':visible') || t.hasClass('none'))
      return
    t.children().each(function(){
      var v = $(this).html()
      if (all_tags.indexOf(v) < 0)
        all_tags.push(v)
    })
  })
  e.find('.post_tags').html().split(' ').forEach(function(v){
    if (all_tags.indexOf(v) < 0)
      all_tags.push(v)
  })
  $('#p'+id+'_post_tags').val(all_tags.join(' '))
}

function add_tag(id, tag) {
  var e = $('#p'+id+'_post_tags'),
      r = e.val().match(new RegExp('\s*?' + tag + '\s*?'))
  if (!r) {
    e.val(e.val().replace(/\s+$/, '') + ' ' + tag)
  }
}

function get_tags_for(id) {
  var e = post_container(id)
  return e.find('.post_tags').html()
}

function get_post_id(e) {
  return e.parents('.post_compare').attr('id').match(/(\d+)/)[1]
}

function number_to_human_size(number)
{
  if (typeof number != 'number')
    number = parseInt(number)
  size = number / 1024; 
  if (size < 1024){ 
    size = (size).toFixed(1); 
    size += ' KB'; 
  } else { 
    if (size / 1024 < 1024){ 
      size = (size / 1024).toFixed(1); 
      size += ' MB'; 
    } else if (size / 1024 / 1024 < 1024) { 
      size = (size / 1024 / 1024).toFixed(1); 
      size += ' GB'; 
    }  
  } 
  return size; 
}

function update_post(id) {
  
}

$('.post_compare .header').click(function(){
  $(this).next().slideToggle(200)
  return false
})
$(document).delegate('.external_data .header', 'click', function(){
  $(this).next().slideToggle(200)
  return false
})
$(document).delegate('td.tags a', 'click', function(ev) {
  ev.preventDefault()
  var id = get_post_id($(this))
  add_tag(id, $(this).html())
})
$(document).delegate('.add_all_tags', 'click', function(ev) {
  ev.preventDefault()
  var id = get_post_id($(this))
  $(this).parent().parent().next().children().each(function() {
    add_tag(id, $(this).html())
  })
})
$(document).delegate('.change_post_source', 'click', function(ev) {
  ev.preventDefault()
  var id = get_post_id($(this)),
      src = $(this).html()
  $('#p' + id + '_post_source').val(src)
})
$('.new_post_data').submit(function(ev){
  ev.preventDefault()
  var id = get_post_id($(this)),
      data = {
        id:id,
        post:{
          rating:$('#p'+id+' [name="post[rating]"]:checked').val(),
          tags:$('#p'+id+'_post_tags').val(),
          source:$('#p'+id+'_post_source').val()
        }
      }
  notice('Updating...')
  $.post('<?= $this->urlFor(['post#update', 'format' => 'json']) ?>', data, function(resp) {
    $('#p'+id+' .post_tags').html(resp.post.tags)
    $('#p'+id+' .post_source').html(data.post.source || '<em class="none">none</em>')
    switch (data.post.rating) {
      case 'e':
        $('#p'+id+' .post_rating').html('Explicit')
        break
      case 'q':
        $('#p'+id+' .post_rating').html('Questionable')
        break
      case 's':
        $('#p'+id+' .post_rating').html('Safe')
        break
    }
    notice('Post updated')
  })
})
$('.merge_results_tags').click(function(){
  merge_tags(get_post_id($(this)))
})
})(jQuery);
Post.external_data(<?= $this->posts[0]->id ?>)
</script>
<?php else: ?>
No id selected
<?php endif ?>
