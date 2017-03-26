<?php

namespace NekoAPI\Tool\ProjectGenerator\Console\Command;

use GitWrapper\GitWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Funct\Strings;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

/**
 * Class GenerateCommand
 *
 * @package NekoAPI\Tool\ProjectGenerator\Console\Command
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class GenerateCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('main');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $output->writeln(
            [
                $this->getApplication()->getLongVersion(),
                '',
            ]
        );

        $question = new Question('<question>Please enter project name in title case:</question> ');

        $name = $helper->ask($input, $output, $question);

        if (empty($name)) {
            $output->writeln(
                [
                    '',
                    '<error>Error: Project name cannot be empty</error>',
                ]
            );

            return 1;
        }

        if (false === ctype_upper($name[0])) {
            $output->writeln(
                [
                    '',
                    '<error>Error: Project name must be title case</error>',
                ]
            );

            return 1;
        }

        $nameCan     = Strings\dasherize($name);
        $projectPath = getcwd() . '/' . $nameCan;

        if (file_exists($projectPath) || is_dir($projectPath)) {
            $output->writeln(
                [
                    '',
                    '<error>Error: Project with name "' . $nameCan . '" already exists</error>',
                ]
            );

            return 1;
        }

        $projectTypes = ['Component', 'Tool', 'Bundle', 'Action'];
        $question     = new Question('<question>Please enter project type (Component):</question> ', 'Component');
        $question->setAutocompleterValues($projectTypes);

        $projectType = $helper->ask($input, $output, $question);

        if (empty($projectType)) {
            $output->writeln(
                [
                    '',
                    '<error>Error: Project type cannot be empty</error>',
                ]
            );

            return 1;
        }

        if (false === ctype_upper($projectType[0])) {
            $output->writeln(
                [
                    '',
                    '<error>Error: Project type must be title case</error>',
                ]
            );

            return 1;
        }

        $author    = rtrim(shell_exec("git config --get user.name"));
        $authorSug = '';
        if (null !== $author) {
            $authorSug = '(' . $author . ')';
        }
        $question = new Question('<question>Please enter author ' . $authorSug . ':</question> ', $author);

        $author = $helper->ask($input, $output, $question);

        if (empty($author)) {
            $output->writeln(
                [
                    '',
                    '<error>Error: Author cannot be empty</error>',
                ]
            );

            return 1;
        }

        $email    = rtrim(shell_exec("git config --get user.email"));
        $emailSug = '';
        if (null !== $email) {
            $emailSug = '(' . $email . ')';
        }
        $question = new Question('<question>Please enter email ' . $emailSug . ':</question> ', $email);

        $email = $helper->ask($input, $output, $question);

        if (empty($email)) {
            $output->writeln(
                [
                    '',
                    '<error>Error: Email cannot be empty</error>',
                ]
            );

            return 1;
        }

        $question = new ConfirmationQuestion('<question>Do you want to create repository on Github?</question>');

        $createRepo = $helper->ask($input, $output, $question);

        if (true === $createRepo) {
            $repo = 'git@github.com:neko-api/' . $name . '.git';
        } else {
            $question = new Question('<question>Please enter git repository:</question> ');

            $repo = $helper->ask($input, $output, $question);

            if (empty($repo)) {
                $output->writeln(
                    [
                        '',
                        '<error>Error: Git repository cannot be empty</error>',
                    ]
                );

                return 1;
            }
        }

        $output->writeln(['']);

        $year = date('Y');

        $output->write('<info>Cloning project template: </info>');
        $wrapper = new GitWrapper();
        $wrapper->cloneRepository('git@github.com:neko-api/project-template.git', $projectPath);
        $output->writeln('<comment>Done</comment>');

        $fs = new Filesystem();
        $output->write('<info>Removing .git folder: </info>');
        $fs->remove($projectPath . '/.git');
        $output->writeln('<comment>Done</comment>');

        $output->write('<info>Removing src/.gitkeep and  tests/.gitkeep file: </info>');
        $fs->remove($projectPath . '/src/.gitkeep');
        $fs->remove($projectPath . '/tests/.gitkeep');
        $output->writeln('<comment>Done</comment>');

        $output->write('<info>Initialized empty Git repository: </info>');
        $git = $wrapper->init($projectPath);
        $output->writeln('<comment>Done</comment>');

        $finder = new Finder();
        $finder->in($projectPath)->ignoreDotFiles(false)->files();


        $output->writeln(['', '<info>Applying variables to files: </info>']);
        $progress = new ProgressBar($output, count($finder));
        $progress->setFormat('debug');
        $progress->start();

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $searchFor = [
                '${NAME}$',
                '${NAME_CAN}$',
                '${PROJECT_TYPE}$',
                '${YEAR}$',
                '${AUTHOR}$',
                '${EMAIL}$',
            ];

            $replaceWith = [
                $name,
                $nameCan,
                $projectType,
                $year,
                $author,
                $email,
            ];

            $progress->setMessage($file->getFilename());

            file_put_contents(
                $file->getRealPath(),
                str_replace($searchFor, $replaceWith, $file->getContents())
            );

            $progress->advance();
        }

        $progress->finish();
        $output->writeln('<comment>Done</comment>');

        if (true === $createRepo) {
            $output->write('<info>Creating Github repository: </info>');
            $description = 'The NekoAPI ' . $name . ' ' . $projectType . '.';

            $process = new Process(
                'hub create -d "' . $description . '" neko-api/' . $nameCan, getcwd() . '/' . $name
            );

            $process->run();

            $output->writeln('<comment>Done</comment>');
        } else {
            $git->remote('add', 'origin', $repo);
        }

        $git->add('./');

        $output->writeln(['', '', '<comment>Finished.</comment>']);

        return 0;
    }
}
