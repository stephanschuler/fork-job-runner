<?PHP
declare(strict_types=1);

require getenv('AUTOLOADER');

use StephanSchuler\ForkJobRunner\Loop;

Loop::create()
    ->readFrom((string)getenv('INPUT_FILE'))
    ->run();
