import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractWrapperClass, parsePreservedNotes, buildCatalog } from '../../tools/blocks-catalog.mjs';

test('extractWrapperClass finds the starter- wrapper class', () => {
  const php = `$w = get_block_wrapper_attributes( array( 'class' => 'starter-hero' ) );`;
  assert.equal(extractWrapperClass(php), 'starter-hero');
});

test('extractWrapperClass returns null when absent', () => {
  assert.equal(extractWrapperClass('<?php echo "nope";'), null);
});

test('parsePreservedNotes recovers human notes keyed by block name', () => {
  const doc = [
    '## pediment/hero',
    '',
    '**Use when:** the section is a page-leading headline with a primary CTA.',
    '',
    '## pediment/cta',
    '',
    '**Use when:** _(add guidance)_',
    '',
  ].join('\n');
  const notes = parsePreservedNotes(doc);
  assert.equal(notes['pediment/hero'], 'the section is a page-leading headline with a primary CTA.');
  assert.equal(notes['pediment/cta'], '_(add guidance)_');
});

test('buildCatalog emits a section per block with attrs, class, source, preserved notes', () => {
  const records = [
    {
      source: 'parent',
      blockJson: {
        name: 'pediment/hero', title: 'Hero', description: 'Page-leading headline.',
        attributes: { heading: { type: 'string', default: '' } },
        supports: { align: ['wide', 'full'] },
      },
      renderPhp: `array( 'class' => 'starter-hero' )`,
    },
    {
      source: 'child',
      blockJson: {
        name: 'pediment-child/promo-banner', title: 'Promo Banner', description: 'Example.',
        attributes: { headline: { type: 'string', default: '' } },
      },
    },
  ];
  const existing = '## pediment/hero\n\n**Use when:** leading headline with CTA.\n';
  const md = buildCatalog(records, existing);

  assert.match(md, /^# Pediment block catalog/m);
  assert.match(md, /## pediment\/hero/);
  assert.match(md, /\*\*Source:\*\* parent/);
  assert.match(md, /\*\*Wrapper class:\*\* `starter-hero`/);
  assert.match(md, /`heading` \(string\)/);
  // preserved human note survives regeneration:
  assert.match(md, /\*\*Use when:\*\* leading headline with CTA\./);
  // new block with no prior note gets the editable marker:
  assert.match(md, /## pediment-child\/promo-banner[\s\S]*\*\*Use when:\*\* _\(add guidance\)_/);
});
