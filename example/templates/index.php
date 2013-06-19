<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf8">
		<title>Vice example</title>
	</head>

	<body>
		<h1>Vice example</h1>

		<h2>Welcome <?= htmlentities($current_user_name, ENT_COMPAT, 'utf-8') ?>!</h2>

		<ul></ul>

		<dl>
			<dt>Name:</dt>
			<dd class="name" data-field="name" contenteditable></dd>

			<dt>Email address:</dt>
			<dd class="emailaddress" data-field="emailaddress" contenteditable></dd>
		</dl>

		<p>
			<a href="#">Add</a>
		</p>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
		<script>
		$(function() {
			function fetchAll() {
				$.getJSON('ajax/users', function(data) {
					$('ul').empty();

					$.each(data, function() {
						$('ul').append('<li><a href="ajax/users/' + this.id + '">' +
							this.name + '</a> (' + this.emailaddress +
							') - <a href="ajax/users/' + this.id + '" class="delete">delete</a>');
					});
				});
			}

			fetchAll();

			$('ul').on('click', 'a:not(.delete)', function() {
				$.getJSON(this.href, function(data) {
					$('dd.name').text(data.name);
					$('dd.emailaddress').text(data.emailaddress);
					$('dd').data('id', data.id);
					$('dd.name').focus();
				});

				return false;
			});

			$('p a').on('click', function() {
				var name = window.prompt('Name?');
				var emailaddress = window.prompt('Email address?');
				var user = {
					name: name,
					emailaddress: emailaddress,
					_method: 'PUT'
				};

				$.post('ajax/users', user, function(data) {
					fetchAll();
				});

				return false;
			});

			$('ul').on('click', 'a.delete', function() {
				if (confirm('Sure?')) {
					$.post(this.href, { _method: 'DELETE' }, function() {
						fetchAll();
					});
				}

				return false;
			});

			var timeout;
			$('[contenteditable]').on('input', function() {
				var that = $(this);

				clearTimeout(timeout);
				timeout = setTimeout(function() {
					var id = that.data('id');
					var field = that.data('field');
					var post = {
						_method: 'PUT'
					};
					post[field] = that.text();

					$.post(
						'ajax/users/' + id,
						post,
						function(data) {
							fetchAll();
						}
						);
				}, 500);
			}).on('keydown', function(ev) {
				if (ev.type == 'keydown' && (ev.keyCode == 13 || ev.keyCode == 27)) {
					$(this).blur();
					return false;
				}
			});
		});
		</script>
	</body>
</html>
