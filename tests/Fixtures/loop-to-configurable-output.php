<?PHP
declare(strict_types=1);

require getenv('AUTOLOADER');

use StephanSchuler\ForkJobRunner\Loop;

Loop::create()
    ->readFrom('php://stdin')
    ->writeTo((string)getenv('RETURN_CHANNEL'))
    ->run();
