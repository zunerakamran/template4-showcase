import axios from 'axios'
import CONFIG from '../../config.js'

export const publicApi = axios.create({
  baseURL: CONFIG.API_URL
})

export default publicApi