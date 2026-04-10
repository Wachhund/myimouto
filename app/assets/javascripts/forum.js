(function($) {
  Forum = {
    mark_all_read: function() {
      $.ajax({
        url: Moebooru.path('/forum/mark_all_read'),
      }).done(function() {
        $('span.forum-topic').removeClass('unread-topic');
        $('div.forum-update').removeClass('forum-update');
        Menu.sync_forum_menu();
        notice("Marked all topics as read");
      });
    },
    quote: function(id) {
      $.ajax({
        url: Moebooru.path('/forum/show.json'),
        type: 'get',
        data: {
          'id': id
        }
      }).done(function(resp) {
        var stripped_body = resp.body.replace(/\[quote\](?:.|\n|\r)+?\[\/quote\][\n\r]*/gm, '');
        $('#reply').show();
        $('#forum_post_body').val(function(i, val) { return val + '[quote]' + resp.creator + ' said:\n' + stripped_body + '\n[/quote]\n\n'; });
        if($('#respond-link'))
          $('#respond-link').hide();
        if($('#forum_post_body'))
          $('#forum_post_body').focus();
      }).fail(function() {
        notice("Error quoting forum post");
      });
    },
    vote: function(post_id, score) {
      $.ajax({
        url: Moebooru.path('/forum/vote/' + post_id + '.json'),
        type: 'post',
        data: { score: score },
        dataType: 'json'
      }).done(function(resp) {
        if (resp.success) {
          Forum.updateVoteUI(post_id, score, resp.post_score);
          notice("Vote recorded");
        }
      }).fail(function() {
        notice("Error recording vote");
      });
      return false;
    },
    unvote: function(post_id) {
      $.ajax({
        url: Moebooru.path('/forum/unvote/' + post_id + '.json'),
        type: 'post',
        dataType: 'json'
      }).done(function(resp) {
        if (resp.success) {
          Forum.updateVoteUI(post_id, null, resp.post_score);
          notice("Vote removed");
        }
      }).fail(function() {
        notice("Error removing vote");
      });
      return false;
    },
    updateVoteUI: function(post_id, user_vote, score) {
      var $container = $('.forum-post-votes[data-post-id="' + post_id + '"]');
      $container.find('.forum-vote-score').text(score);
      $container.find('.forum-vote-btn').removeClass('forum-vote-active');
      if (user_vote !== null) {
        $container.find('.forum-vote-btn[data-vote-score="' + user_vote + '"]').addClass('forum-vote-active');
      }
      $container.find('.forum-vote-remove').remove();
      if (user_vote !== null) {
        var $lastBtn = $container.find('.forum-vote-btn').last();
        $lastBtn.after(' <a href="#" class="forum-vote-remove" data-post-id="' + post_id + '" title="Remove vote">&#x2715;</a>');
      }
    },
    initVoteEvents: function() {
      $(document).on('click', '.forum-vote-btn', function(e) {
        e.preventDefault();
        var post_id = $(this).data('post-id');
        var score = $(this).data('vote-score');
        Forum.vote(post_id, score);
      });
      $(document).on('click', '.forum-vote-remove', function(e) {
        e.preventDefault();
        var post_id = $(this).data('post-id');
        Forum.unvote(post_id);
      });
    }
  };

  $(function() {
    Forum.initVoteEvents();
  });
}) (jQuery);
