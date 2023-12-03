<?php

namespace Cesurapp\ApiBundle\Response;

enum MessageType: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
}
