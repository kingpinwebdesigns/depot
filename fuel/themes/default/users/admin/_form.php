<?php echo Form::open(array('class' => 'form-stacked')); ?>

	<fieldset>

		<div class="clearfix">
			<?php echo Form::label('Username', 'username'); ?>

			<div class="input">
				<?php echo Form::input('username', Input::post('username', isset($user) ? $user->username : ''), array('class' => 'span6')); ?>

			</div>
		</div>
		<div class="clearfix">
			<?php echo Form::label('Fullname', 'full_name'); ?>

			<div class="input">
				<?php echo Form::input('full_name', Input::post('full_name', isset($user) ? $user->profile_fields['full_name'] : ''), array('class' => 'span6')); ?>

			</div>
		</div>
		<div class="clearfix">
			<?php echo Form::label('Email', 'email'); ?>

			<div class="input">
				<?php echo Form::input('email', Input::post('email', isset($user) ? $user->email : ''), array('class' => 'span6')); ?>

			</div>
		</div>
		<div class="clearfix">
			<?php echo Form::label('Password', 'password'); ?>

			<div class="input">
				<?php echo Form::password('password', '', array('class' => 'span6')); ?>

			</div>
		</div>
		<div class="clearfix">
			<?php echo Form::label('Password again', 'password_again'); ?>

			<div class="input">
				<?php echo Form::password('password_again', '', array('class' => 'span6')); ?>

			</div>
		</div>
		<div class="clearfix">
			<?php echo Form::label('Group', 'group'); ?>

			<div class="input">
				<?php echo Form::select('group', Input::post('group', isset($user) ? $user->group : 0), $groups, array('class' => 'span6')); ?>

			</div>
		</div>

		<div class="actions">
			<?php echo Form::submit('submit', 'Save', array('class' => 'btn primary')); ?>
		</div>

	</fieldset>
<?php echo Form::close(); ?>
