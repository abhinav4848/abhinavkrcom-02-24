<?php

const ENGINE_EXPORT_FORMAT = 'smart-search-engines-v1';
const ENGINE_EXPORT_LIMIT = 1_048_576;

function engine_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function read_engine_export(string $html): array
{
    // Parse the uploaded document as data. None of its scripts are run.
    $document = new DOMDocument();
    if (!$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        throw new InvalidArgumentException('Could not read the engine export.');
    }
    $payload = $document->getElementById('smart-search-engines');
    if ($payload !== null) {
        $export = json_decode($payload->textContent, true, 32, JSON_THROW_ON_ERROR);
        if (($export['format'] ?? null) !== ENGINE_EXPORT_FORMAT || !is_array($export['engines'] ?? null)) {
            throw new InvalidArgumentException('This engine export format is unsupported.');
        }
        return $export['engines'];
    }

    $engines = [];
    foreach ($document->getElementsByTagName('button') as $button) {
        $classes = preg_split('/\s+/', trim($button->getAttribute('class')));
        if (!in_array('newEngineMake', $classes, true)) {
            continue;
        }
        $archived = in_array('btn-warning', $classes, true);
        $identifier = $button->getAttribute('data-identifier');
        $engine = [
            'identifier' => $identifier,
            'old-identifier' => $archived ? $identifier : '',
            'hidden' => (int) $archived,
            'legacy-archive' => $archived,
        ];
        foreach (['site name' => 'sitename', 'url-prefix' => 'urlprefix', 'url-suffix' => 'urlsuffix',
                  'baseurl' => 'baseurl', 'nsfw' => 'nsfw'] as $column => $attribute) {
            $key = 'data-' . $attribute;
            if (!$button->hasAttribute($key)) {
                throw new InvalidArgumentException('An engine is missing a required export field.');
            }
            $engine[$column] = $button->getAttribute($key);
        }
        $engines[] = $engine;
    }
    if ($engines === []) {
        throw new InvalidArgumentException('This file does not contain a Smart Search engine export.');
    }
    return $engines;
}

function import_engine_export(PDO $db, array $engines): array
{
    $findArchive = $db->prepare(
        'SELECT 1 FROM searcher_engines WHERE hidden = 1 AND "old-identifier" = ? LIMIT 1'
    );
    $insert = $db->prepare(
        'INSERT INTO searcher_engines ("site name", identifier, "old-identifier",
            "url-prefix", "url-suffix", baseurl, nsfw, hidden)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(identifier) DO NOTHING'
    );

    $added = 0;
    $db->beginTransaction();
    try {
        foreach ($engines as $engine) {
            if (!is_array($engine)) {
                throw new InvalidArgumentException('The export contains an invalid engine.');
            }
            foreach (['site name', 'identifier', 'old-identifier', 'url-prefix', 'url-suffix'] as $field) {
                if (!is_string($engine[$field] ?? null)) {
                    throw new InvalidArgumentException('An engine has an incomplete configuration.');
                }
            }
            $base = $engine['baseurl'] ?? null;
            if (!array_key_exists('baseurl', $engine) || ($base !== null && !is_string($base))) {
                throw new InvalidArgumentException('An engine has an invalid base URL.');
            }
            if (trim($engine['site name']) === '' || trim($engine['identifier']) === '') {
                throw new InvalidArgumentException('Every engine needs a name and identifier.');
            }
            foreach (['hidden', 'nsfw'] as $field) {
                if (!in_array($engine[$field] ?? null, [0, 1, '0', '1'], true)) {
                    throw new InvalidArgumentException('An engine has an invalid visibility setting.');
                }
            }
            foreach ([$engine['url-prefix'], $base] as $url) {
                if ($url !== null && $url !== '' && !preg_match('~^https?://[^\s/?#]+~i', $url)) {
                    throw new InvalidArgumentException('Engine URLs must use HTTP or HTTPS.');
                }
            }

            $identifier = $engine['identifier'];
            if (($engine['legacy-archive'] ?? false) === true) {
                $findArchive->execute([$engine['old-identifier']]);
                if ($findArchive->fetchColumn()) {
                    $findArchive->closeCursor();
                    continue;
                }
                $findArchive->closeCursor();
                $identifier = bin2hex(random_bytes(15));
            }
            $insert->execute([
                $engine['site name'], $identifier, $engine['old-identifier'], $engine['url-prefix'],
                $engine['url-suffix'], $base, (int) $engine['nsfw'], (int) $engine['hidden'],
            ]);
            $added += $insert->rowCount();
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    return [$added, count($engines) - $added];
}
