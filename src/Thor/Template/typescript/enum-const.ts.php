<?php
/*
 * Enum-attached data table of arbitrary depth: whatever array the `_enums` slot carries is emitted
 * as a TypeScript const. JSON already is valid TS object-literal syntax, so the table is printed
 * verbatim and `as const` keeps the literal types — numbers stay numbers, booleans stay booleans
 * and nesting is free.
 */
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// JSON_PRETTY_PRINT indents with four spaces; the rest of the generated client uses two.
$json = preg_replace_callback('/^ +/m', static fn (array $m): string => str_repeat(' ', intdiv(strlen($m[0]), 2)), $json);
?>
/* eslint-disable max-len */

export const <?php echo ucfirst($namespace); ?> = <?php echo $json; ?> as const;
