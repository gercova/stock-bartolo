/**
 * Entidades bancarias y financieras estándar para agentes corresponsales (Perú)
 */

export const BANK_ENTITIES = [
  {
    id: 'bcp',
    name: 'BCP',
    fullName: 'Banco de Crédito del Perú',
    shortName: 'BCP',
    code: '002',
    color: '#002a8f',
    badgeClass: 'bg-blue-100 text-blue-800 border-blue-200',
    iconColor: 'text-blue-700',
  },
  {
    id: 'bn',
    name: 'Banco de la Nación',
    fullName: 'Banco de la Nación del Perú',
    shortName: 'BN',
    code: '018',
    color: '#d9272e',
    badgeClass: 'bg-red-100 text-red-800 border-red-200',
    iconColor: 'text-red-700',
  },
  {
    id: 'bbva',
    name: 'BBVA',
    fullName: 'BBVA Perú',
    shortName: 'BBVA',
    code: '011',
    color: '#004481',
    badgeClass: 'bg-sky-100 text-sky-800 border-sky-200',
    iconColor: 'text-sky-700',
  },
  {
    id: 'interbank',
    name: 'Interbank',
    fullName: 'Interbank',
    shortName: 'Interbank',
    code: '003',
    color: '#009b3a',
    badgeClass: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    iconColor: 'text-emerald-700',
  },
  {
    id: 'scotiabank',
    name: 'Scotiabank',
    fullName: 'Scotiabank Perú',
    shortName: 'Scotiabank',
    code: '009',
    color: '#ec111a',
    badgeClass: 'bg-rose-100 text-rose-800 border-rose-200',
    iconColor: 'text-rose-700',
  },
  {
    id: 'banbif',
    name: 'BanBif',
    fullName: 'BanBif',
    shortName: 'BanBif',
    code: '038',
    color: '#00a3e0',
    badgeClass: 'bg-cyan-100 text-cyan-800 border-cyan-200',
    iconColor: 'text-cyan-700',
  },
  {
    id: 'caja_arequipa',
    name: 'Caja Arequipa',
    fullName: 'Caja Municipal Arequipa',
    shortName: 'C. Arequipa',
    code: '801',
    color: '#e30613',
    badgeClass: 'bg-amber-100 text-amber-800 border-amber-200',
    iconColor: 'text-amber-700',
  },
  {
    id: 'caja_huancayo',
    name: 'Caja Huancayo',
    fullName: 'Caja Municipal Huancayo',
    shortName: 'C. Huancayo',
    code: '802',
    color: '#f39200',
    badgeClass: 'bg-orange-100 text-orange-800 border-orange-200',
    iconColor: 'text-orange-700',
  },
  {
    id: 'caja_piura',
    name: 'Caja Piura',
    fullName: 'Caja Municipal Piura',
    shortName: 'C. Piura',
    code: '803',
    color: '#0072ce',
    badgeClass: 'bg-blue-100 text-blue-900 border-blue-300',
    iconColor: 'text-blue-800',
  },
  {
    id: 'caja_cusco',
    name: 'Caja Cusco',
    fullName: 'Caja Municipal Cusco',
    shortName: 'C. Cusco',
    code: '804',
    color: '#8b1d24',
    badgeClass: 'bg-stone-100 text-stone-800 border-stone-200',
    iconColor: 'text-stone-700',
  },
  {
    id: 'pichincha',
    name: 'Banco Pichincha',
    fullName: 'Banco Pichincha Perú',
    shortName: 'Pichincha',
    code: '056',
    color: '#ffdd00',
    badgeClass: 'bg-yellow-100 text-yellow-900 border-yellow-300',
    iconColor: 'text-yellow-800',
  },
  {
    id: 'yape',
    name: 'Yape',
    fullName: 'Yape (BCP)',
    shortName: 'Yape',
    code: 'YAPE',
    color: '#742284',
    badgeClass: 'bg-purple-100 text-purple-800 border-purple-200',
    iconColor: 'text-purple-700',
  },
  {
    id: 'plin',
    name: 'Plin',
    fullName: 'Plin (Interbank/BBVA/Scotiabank)',
    shortName: 'Plin',
    code: 'PLIN',
    color: '#00d2c4',
    badgeClass: 'bg-teal-100 text-teal-800 border-teal-200',
    iconColor: 'text-teal-700',
  },
  {
    id: 'otro',
    name: 'Otro',
    fullName: 'Otra Entidad Financiera',
    shortName: 'Otro',
    code: 'OTRO',
    color: '#64748b',
    badgeClass: 'bg-slate-100 text-slate-800 border-slate-200',
    iconColor: 'text-slate-600',
  },
]

export function getBankEntity(nameOrId) {
  if (!nameOrId) return null
  const query = String(nameOrId).trim().toLowerCase()
  return (
    BANK_ENTITIES.find(
      (b) =>
        b.id === query ||
        b.name.toLowerCase() === query ||
        b.shortName.toLowerCase() === query ||
        b.fullName.toLowerCase() === query
    ) || {
      id: query,
      name: nameOrId,
      fullName: nameOrId,
      shortName: nameOrId,
      code: 'GEN',
      color: '#64748b',
      badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
      iconColor: 'text-slate-600',
    }
  )
}

export function getBankBadgeClass(nameOrId) {
  const bank = getBankEntity(nameOrId)
  return bank ? bank.badgeClass : 'bg-slate-100 text-slate-700 border-slate-200'
}

