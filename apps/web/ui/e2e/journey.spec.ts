import { readFile } from 'node:fs/promises'
import { expect, test } from '@playwright/test'

// Le parcours complet d'un visiteur : accueil → documentation → générateur → starter zip.
test('du site au starter téléchargé', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1 })).toContainText('en une commande')

  // Documentation
  await page.getByRole('link', { name: 'Commencer →' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Démarrage rapide' })).toBeVisible()
  await page.getByRole('link', { name: 'Installation' }).first().click()
  await expect(page.getByRole('heading', { level: 1, name: 'Installation' })).toBeVisible()

  // Générateur : options lues dans la vraie matrice
  await page.getByRole('link', { name: 'Générateur' }).first().click()
  await expect(page.getByRole('heading', { level: 1, name: 'Assemblez votre starter' })).toBeVisible()
  await page.getByRole('button', { name: /^Laravel/ }).click()
  await expect(page.getByRole('button', { name: '8.2', exact: true })).toBeDisabled()
  await page.getByRole('button', { name: 'SQLite (fichier local)' }).click()
  await page.getByLabel('Redis').check()
  await page.getByLabel('Nom du projet').fill('e2e-boutique')

  // L'adresse décrit la configuration : on peut la partager.
  await expect(page).toHaveURL(/framework=laravel/)
  await expect(page).toHaveURL(/db=sqlite/)

  // Téléchargement du starter
  const downloadPromise = page.waitForEvent('download')
  await page.getByRole('button', { name: /Générer le starter/ }).click()
  const download = await downloadPromise
  expect(download.suggestedFilename()).toBe('e2e-boutique.zip')

  const zip = await readFile(await download.path())
  const names = zip.toString('latin1')
  for (const file of ['gabarit.json', 'bin/up', 'bin/up.cmd', 'README.md']) {
    expect(names).toContain(file)
  }
})

test('un lien partagé rouvre la même configuration', async ({ page }) => {
  await page.goto('/#/new?framework=laravel&php=8.4&db=sqlite&frontend=vue&name=partage')
  await expect(page.getByRole('button', { name: /^Laravel/ })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByRole('button', { name: '8.4', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByLabel('Nom du projet')).toHaveValue('partage')
})
