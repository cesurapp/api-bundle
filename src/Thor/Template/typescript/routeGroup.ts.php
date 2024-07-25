/* eslint-disable no-param-reassign */
/* eslint-disable max-len */
/* eslint-disable @typescript-eslint/no-unused-vars */
/* eslint-disable no-useless-constructor */

import type { AxiosInstance, AxiosRequestConfig, Method, AxiosResponse } from 'axios';
import { toQueryString } from './flatten';

<?php foreach ($data as $groupedRoutes) {
    foreach ($groupedRoutes as $route) { ?>
<?php if ($route['response']) { ?>
import type { <?php echo ucfirst($route['shortName']); ?>Response } from './<?php echo $route['routeGroup']; ?>/response/<?php echo ucfirst($route['shortName']); ?>Response';
<?php } ?>
<?php if ($route['request']) { ?>
import type { <?php echo ucfirst($route['shortName']); ?>Request } from './<?php echo $route['routeGroup']; ?>/request/<?php echo ucfirst($route['shortName']); ?>Request';
<?php } ?>
<?php if ($route['query']) { ?>
import type { <?php echo ucfirst($route['shortName']); ?>Query } from './<?php echo $route['routeGroup']; ?>/query/<?php echo ucfirst($route['shortName']); ?>Query';
<?php } ?>
<?php if (isset($route['routeAttr'])) {
    foreach ($route['routeAttr'] as $name => $class) { ?>
<?php if (enum_exists($class)) { ?>
import type { <?php echo ucfirst(Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor::baseClass($class)); ?> } from 'enum/<?php echo ucfirst(Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor::baseClass($class)); ?>';
<?php } ?>
<?php }
    } ?>
<?php }
    } ?>

export default class <?php echo ucfirst($route['routeGroup']); ?> {
  constructor(private client: AxiosInstance) {}
<?php foreach ($data as $groupedRoutes) {
    foreach ($groupedRoutes as $route) {
        if (!$route['isFile']) { ?>

  async <?php echo $route['shortName']; ?>(<?php echo $attrs = $helper->renderAttributes($route); ?>): Promise<AxiosResponse<<?php echo ucfirst($route['shortName']); ?>Response>> {
    return this.rq('<?php echo $route['methods'][0]; ?>', <?php echo $helper->renderEndpointPath($route['path'], $attrs); ?>, config, <?php echo str_contains($attrs, 'request') ? 'request' : 'null'; ?>)
  }
<?php } else { ?>

  async <?php echo $route['shortName']; ?>(<?php echo $attrs = $helper->renderAttributes($route); ?>): Promise<AxiosResponse> {
    return this.rq('<?php echo $route['methods'][0]; ?>', <?php echo $helper->renderEndpointPath($route['path'], $attrs); ?>, config)
  }

  <?php echo $route['shortName']; ?>Link(<?php echo $attrs = $helper->renderAttributes($route); ?>): String {
    return this.rl('<?php echo $route['methods'][0]; ?>', <?php echo $helper->renderEndpointPath($route['path'], $attrs); ?>, config)
  }
<?php }
}
} ?>

  async rq(method: Method, url: string, config: AxiosRequestConfig = {}, data?: any) {
    config.method = method;
    config.url = url;
    if (data) {
      config.data = data;
    }

    return await this.client.request(config);
  }

  rl(method: Method, url: string, config: AxiosRequestConfig = {}) {
    config.method = method;
    config.url = url;

    return this.client.getUri(config);
  }
}