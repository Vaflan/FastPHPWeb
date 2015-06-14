<form action="" method="get">
	<h3>User information</h3>
	Name: <input type="text" name="user[name]" /><br />
	Surname: <input type="text" name="user[surname]" /><br />
	Age: <input type="text" name="user[age]" /><br />
	Gender: <select name="user[gender]"><option>Male</option><option>Female</option></select><br />
	<label>Show age? <input type="checkbox" value="1" name="user[show_age]" /></label><br />
	<input type="submit" value="Send private information" /> <input type="reset" value="Clear form" />
</form>

<br />
<?php
if(!empty($_GET['user'])) {
	echo '<textarea style="width:330px; height: 200px;">'.print_r($_GET, true).'</textarea>';
}
?>