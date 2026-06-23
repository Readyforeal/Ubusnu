<?php

it('ships all docker deploy artifacts', function () {
    $paths = [
        'Dockerfile',
        '.dockerignore',
        'docker/Caddyfile',
        'docker/entrypoint.sh',
        'compose.example.yml',
        '.env.production.example',
        'docs/deploy.md',
        '.github/workflows/build-and-publish.yml',
    ];

    foreach ($paths as $rel) {
        expect(is_file(base_path($rel)))
            ->toBeTrue("Missing required deploy artifact: {$rel}");
    }
});

it('Dockerfile references the entrypoint at the expected path', function () {
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh');
    expect($dockerfile)->toContain('ENTRYPOINT ["entrypoint.sh"]');
});
