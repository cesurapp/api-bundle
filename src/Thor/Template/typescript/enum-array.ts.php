/* eslint-disable max-len */

export const <?php echo ucfirst($namespace); ?> = {
<?php foreach ($data as $provider => $models) { ?>
  <?php echo ucfirst($provider); ?>: {
  <?php foreach ($models as $modelName => $modelValue) { ?>
    <?php echo sprintf("%s: '%s',\n", $modelName, $modelValue); ?>
  <?php } ?>} as const,
<?php } ?>
} as const;
