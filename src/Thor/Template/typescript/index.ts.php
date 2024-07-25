/* eslint-disable no-param-reassign */
/* eslint-disable max-len */
/* eslint-disable @typescript-eslint/no-unused-vars */
/* eslint-disable no-useless-constructor */

import type { AxiosInstance } from 'axios';
<?php
foreach ($routeGroups as $group) {
    echo sprintf("import %s from './%s'", ucfirst($group), $group).PHP_EOL;
}
?>

export default class Api {
<?php foreach ($routeGroups as $group) {
    echo sprintf('    public %s: %s;', $group, ucfirst($group)).PHP_EOL;
} ?>

    constructor(client: AxiosInstance) {
<?php foreach ($routeGroups as $group) {
    echo sprintf('        this.%s = new %s(client);', $group, ucfirst($group)).PHP_EOL;
} ?>
    }
}