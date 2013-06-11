<div id="clocker">
	<table>
		<tr>
			<td>Action</td>
			<td>
				<select id="clock_action">
					<?php foreach (AdpClient::getSendClockActions() as $val => $label): ?>
						<option value="<?=$val?>"><?=$label?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td></td>
			<td><button type="button">Go</button></td>
		</tr>
	</table>
</div>
