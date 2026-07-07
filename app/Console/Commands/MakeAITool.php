<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('app:make-a-i-tool')]
#[Description('Command description')]
class MakeAITool extends Command
{
    protected $signature = 'make:aitool {name}';
    protected $description = 'Create a new AI tool class';
    public function handle()
    {
        $name = $this->argument('name');
        $className = ucfirst($name);

        $path = app_path("AI/Tools/{$className}.php");

        if (File::exists($path)) {
            $this->error("Tool already exists!");
            return 1;
        }

        $stub = <<<PHP
<?php

namespace App\AI\Tools;

class {$className} implements ToolInterface
{
 
public function name(): string
{
 return '{$name}';

}

public function description(): string
{
return 'Describe what this tool does';
}

public function parameters(): array
{
 return [
  // define expected inputs
 ];

 public function execute(array \$arguments): mixed
 {
 return null;
 }
 
}
PHP;

        File::ensureDirectoryExists(app_path('AI/Tools'));
        File::put($path, $stub);

        $this->info("AI tool created: {$className}");
        return 0;
    }
}
