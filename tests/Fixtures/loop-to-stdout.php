<?PHP
declare(strict_types=1);

require getenv('AUTOLOADER');

use StephanSchuler\ForkJobRunner\Loop;

$loop = new Loop('php://stdin', 'php://stdout');
$loop->run();