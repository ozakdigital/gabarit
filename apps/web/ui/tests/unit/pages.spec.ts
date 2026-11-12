import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import App from '../../src/App.vue'
import DocsPage from '../../src/pages/DocsPage.vue'
import HomePage from '../../src/pages/HomePage.vue'
import { docs } from '../../src/docs'
import { goTo } from '../helpers'

describe('App', () => {
  it('affiche la page correspondant à l’ancre', async () => {
    goTo('#/')
    const wrapper = mount(App)
    await flushPromises()
    expect(wrapper.find('.hero').exists()).toBe(true)

    goTo('#/docs/installation')
    await flushPromises()
    expect(wrapper.get('.prose h1').text()).toBe('Installation')

    goTo('#/new')
    await flushPromises()
    expect(wrapper.get('.generator h1').text()).toBe('Assemblez votre starter')
  })
})

describe('HomePage', () => {
  it('liste les technologies depuis la matrice', async () => {
    const wrapper = mount(HomePage)
    await flushPromises()
    const chips = wrapper.findAll('.chip').map((c) => c.text())
    expect(chips).toContain('Laravel 13')
    expect(chips).toContain('MariaDB 11.8')
    expect(chips).toContain('Redis')
  })
})

describe('DocsPage', () => {
  it('affiche la page demandée, son menu et ses voisines', async () => {
    goTo('#/docs/installation')
    const wrapper = mount(DocsPage)
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Installation')
    expect(wrapper.get('.sidebar a.active').text()).toBe('Installation')
    expect(wrapper.get('.crumb').text()).toBe('docs / prise-en-main / installation')
    expect(wrapper.get('.pager .prev').text()).toContain('Démarrage rapide')
    expect(wrapper.get('.pager .next').text()).toContain('Premier projet')
  })

  it('revient à la première page pour une adresse inconnue', async () => {
    goTo('#/docs/inconnue')
    const wrapper = mount(DocsPage)
    await flushPromises()
    expect(wrapper.get('h1').text()).toBe(docs[0]!.title)
    expect(wrapper.find('.pager .prev').exists()).toBe(false)
  })

  it('a une entrée de menu par page', async () => {
    goTo('#/docs/demarrage')
    const wrapper = mount(DocsPage)
    expect(wrapper.findAll('.sidebar a')).toHaveLength(docs.length)
  })

  it('affiche la matrice en direct', async () => {
    goTo('#/docs/combinaisons')
    const wrapper = mount(DocsPage)
    await flushPromises()
    expect(wrapper.text()).toContain('CodeIgniter')
    expect(wrapper.text()).toContain('8.3, 8.4')
  })
})
