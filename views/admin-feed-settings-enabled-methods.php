<?php
if (!defined('ABSPATH')) {
	exit;
}

// NB: these checkbox inputs are not meant to have input names, because they are not sent with form submission!
// they are used to record changes to selected delayed notifications via JSON in a hidden field
?>

<ul id="heidelpay-enabled-methods-container">
<?php foreach ($methods as $method => $label) { ?>

	<li class="heidelpay-enabled-methods">
		<input type="checkbox" class="heidelpay-enabled-methods-checkbox" id="heidelpay-enabled-methods-<?= esc_attr($method); ?>"
			value="<?= esc_attr($method); ?>" <?php checked(!empty($selections[$method])); ?> />
		<label class="inline" for="heidelpay-enabled-methods-<?= esc_attr($method); ?>"><?= esc_html($label); ?></label>

		<?php if ($method === 'CC'): ?>
		<ul id="heidelpay-enabled-methods-creditcards">
			<?php foreach ($creditcards as $creditcard => $label) { ?>
			<li>
				<input type="checkbox" class="heidelpay-enabled-methods-checkbox" id="heidelpay-enabled-methods-<?= esc_attr($creditcard); ?>"
					value="<?= esc_attr($creditcard); ?>" <?php checked(!empty($selections[$creditcard])); ?> />
				<label class="inline" for="heidelpay-enabled-methods-<?= esc_attr($creditcard); ?>"><?= esc_html($label); ?></label>
			</li>
			<?php } ?>
		</ul>
		<?php endif; ?>
	</li>

<?php } ?>
</ul>
