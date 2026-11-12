import { describe, expect, it } from 'vitest'
import { link, parseHash, replaceQuery, route } from '../../src/router'
import { goTo } from '../helpers'

describe('router', () => {
  it("découpe l'ancre en section, page et paramètres", () => {
    const r = parseHash('#/new?framework=laravel&db=sqlite')
    expect(r.path).toBe('/new')
    expect(r.section).toBe('new')
    expect(r.query.get('framework')).toBe('laravel')
    expect(r.query.get('db')).toBe('sqlite')
  })

  it("renvoie l'accueil pour une ancre vide", () => {
    expect(parseHash('')).toMatchObject({ path: '/', section: '', slug: '' })
  })

  it('lit la page de documentation', () => {
    expect(parseHash('#/docs/installation')).toMatchObject({ section: 'docs', slug: 'installation' })
  })

  it('suit les changements d’ancre', () => {
    goTo('#/docs/depannage')
    expect(route.value.slug).toBe('depannage')
  })

  it("remplace les paramètres sans changer de page", () => {
    goTo('#/new')
    replaceQuery(new URLSearchParams({ framework: 'slim' }))
    expect(window.location.hash).toBe('#/new?framework=slim')
    expect(route.value.query.get('framework')).toBe('slim')
  })

  it('construit les liens internes', () => {
    expect(link('/new')).toBe('#/new')
  })
})
