import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import GeneratorPage from '../../src/pages/GeneratorPage.vue'
import { clipboardWrite, downloads, fetchMock, goTo, onStarter } from '../helpers'

async function mountGenerator(hash = '#/new'): Promise<VueWrapper> {
  goTo(hash)
  const wrapper = mount(GeneratorPage)
  for (let i = 0; i < 5; i++) {
    await flushPromises()
    await nextTick()
  }
  return wrapper
}

function tile(wrapper: VueWrapper, panel: string, label: string) {
  const button = wrapper
    .get(`[aria-labelledby="${panel}"]`)
    .findAll('button')
    .find((b) => b.text().startsWith(label))
  if (!button) throw new Error(`Tuile « ${label} » introuvable dans ${panel}`)
  return button
}

async function settle(): Promise<void> {
  await nextTick()
  await nextTick()
}

describe('GeneratorPage', () => {
  it('construit les options depuis la matrice et choisit le premier framework', async () => {
    const wrapper = await mountGenerator()
    expect(wrapper.get('[aria-labelledby="fw-title"]').findAll('button').map((b) => b.find('strong').text())).toEqual(['Symfony', 'Laravel', 'CodeIgniter'])
    expect(tile(wrapper, 'fw-title', 'Symfony').classes()).toContain('on')
    expect(tile(wrapper, 'php-title', '8.3').classes()).toContain('on')
  })

  it('grise les versions de PHP incompatibles', async () => {
    const wrapper = await mountGenerator()
    await tile(wrapper, 'fw-title', 'Laravel').trigger('click')
    await settle()

    expect(tile(wrapper, 'php-title', '8.2').attributes('disabled')).toBeDefined()
    expect(tile(wrapper, 'php-title', '8.3').attributes('disabled')).toBeUndefined()
    expect(wrapper.text()).toContain('Grisé : non pris en charge par Laravel 13')
  })

  it('grise les bases et fronts absents du framework, et garde un choix valide', async () => {
    const wrapper = await mountGenerator()
    await tile(wrapper, 'db-title', 'MariaDB').trigger('click')
    await tile(wrapper, 'front-title', 'Twig').trigger('click')
    await tile(wrapper, 'fw-title', 'CodeIgniter').trigger('click')
    await settle()

    expect(tile(wrapper, 'db-title', 'MariaDB').attributes('disabled')).toBeDefined()
    expect(tile(wrapper, 'db-title', 'PostgreSQL').classes()).toContain('on')
    expect(tile(wrapper, 'front-title', 'Twig').attributes('disabled')).toBeDefined()
    expect(tile(wrapper, 'front-title', 'Aucun').classes()).toContain('on')
  })

  it('bloque le namespace quand le framework l’impose', async () => {
    const wrapper = await mountGenerator()
    await tile(wrapper, 'fw-title', 'CodeIgniter').trigger('click')
    await settle()

    const namespace = wrapper.findAll('input.fld').find((i) => (i.element as HTMLInputElement).value === 'App')
    expect(namespace?.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('CodeIgniter impose le namespace « App »')
  })

  it('refuse un nom de projet invalide', async () => {
    const wrapper = await mountGenerator()
    await wrapper.get('input.fld.mono').setValue('Mon App!')
    expect(wrapper.get('button.generate').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('2 à 40 caractères')
  })

  it('envoie les choix et télécharge le starter', async () => {
    let sent: Record<string, unknown> = {}
    onStarter((body) => {
      sent = JSON.parse(String(body.get('spec')))
      return new Response('zip', { status: 200, headers: { 'Content-Disposition': 'attachment; filename="boutique.zip"' } })
    })
    const wrapper = await mountGenerator()
    await tile(wrapper, 'fw-title', 'Laravel').trigger('click')
    await settle()
    await tile(wrapper, 'db-title', 'SQLite').trigger('click')
    await wrapper.get('input[value="redis"]').setValue(true)
    await wrapper.get('input.fld.mono').setValue('boutique')

    await wrapper.get('button.generate').trigger('click')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith('/api/starter', expect.objectContaining({ method: 'POST' }))
    expect(sent).toMatchObject({
      name: 'boutique',
      framework: 'laravel',
      frameworkVersion: '13',
      phpVersion: '8.3',
      database: 'sqlite',
      services: ['redis'],
      brand: { displayName: 'Boutique', phpNamespace: 'App' },
    })
    expect(downloads).toEqual(['boutique.zip'])
  })

  it('affiche les erreurs renvoyées par l’API', async () => {
    onStarter(() => new Response(JSON.stringify({ message: 'Choix invalides.', violations: { name: 'Nom réservé.' } }), { status: 422 }))
    const wrapper = await mountGenerator()
    await wrapper.get('button.generate').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Choix invalides.')
    expect(wrapper.text()).toContain('Nom réservé.')
    expect(downloads).toEqual([])
  })

  it('signale la limite de demandes', async () => {
    onStarter(() => new Response('', { status: 429 }))
    const wrapper = await mountGenerator()
    await wrapper.get('button.generate').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Trop de demandes')
  })

  it('montre la commande CLI équivalente', async () => {
    const wrapper = await mountGenerator()
    await tile(wrapper, 'fw-title', 'Laravel').trigger('click')
    await settle()
    const cli = wrapper.get('.cli-equivalent code').text()
    expect(cli).toContain('gabarit new mon-app --framework=laravel --fw-version=13 --php=8.3')
    expect(cli).toContain('--services=none')
  })

  describe('lien de partage', () => {
    it('relit une configuration partagée', async () => {
      const wrapper = await mountGenerator('#/new?framework=laravel&php=8.4&db=sqlite&frontend=vue&services=redis,inconnu&name=boutique&brand=Boutique%20D%C3%A9mo&namespace=Acme%5CShop&primary=%23e11d48')

      expect(tile(wrapper, 'fw-title', 'Laravel').classes()).toContain('on')
      expect(tile(wrapper, 'php-title', '8.4').classes()).toContain('on')
      expect(tile(wrapper, 'db-title', 'SQLite').classes()).toContain('on')
      expect(tile(wrapper, 'front-title', 'Vue 3').classes()).toContain('on')
      expect((wrapper.get('input[value="redis"]').element as HTMLInputElement).checked).toBe(true)
      expect(wrapper.get('.preview strong').text()).toBe('Boutique Démo')
      expect(wrapper.get('.cli-equivalent code').text()).toContain("--namespace='Acme\\Shop'")
      expect(wrapper.get('.cli-equivalent code').text()).toContain('--primary-color="#e11d48"')
    })

    it('ignore les valeurs hors matrice', async () => {
      const wrapper = await mountGenerator('#/new?framework=rails&php=5.6&db=oracle&primary=red')
      expect(tile(wrapper, 'fw-title', 'Symfony').classes()).toContain('on')
      expect(tile(wrapper, 'php-title', '8.3').classes()).toContain('on')
      expect(wrapper.get('.cli-equivalent code').text()).toContain('--primary-color="#7c3aed"')
    })

    it("tient l'adresse à jour et copie le lien", async () => {
      const wrapper = await mountGenerator()
      await tile(wrapper, 'fw-title', 'Laravel').trigger('click')
      await settle()
      expect(window.location.hash).toContain('framework=laravel')
      expect(window.location.hash).toContain('version=13')

      await wrapper.get('button.share').trigger('click')
      await flushPromises()
      expect(clipboardWrite).toHaveBeenCalledWith(expect.stringContaining('#/new?framework=laravel'))
      expect(wrapper.get('button.share').text()).toBe('Lien copié')
    })
  })
})
