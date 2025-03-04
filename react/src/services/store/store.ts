import { configureStore } from "@reduxjs/toolkit";

// Slices
import { globalSlice } from "../globalSlice/globalSlice";
import { loginSlice } from "../../containers/LoginForm/LoginFormSlice";
import { predefinedMenusSlice } from '../../components/PredefinedMenus/PredefinedMenusSlice'

import { clientsSlice } from "../../containers/Clients/ClientsSlice";
import { pdsSlice } from "../../containers/PD/PDsSlice";
import { IRSSlice } from "../../containers/IRS/IRSSlice";
import { StagingSlice } from "../../containers/Staging/StagingSlice";


const reducer = {
    global: globalSlice.reducer,
    login: loginSlice.reducer,
    predefined_menus: predefinedMenusSlice.reducer,
    clients: clientsSlice.reducer,
    pds: pdsSlice.reducer,
    irs: IRSSlice.reducer,
    staging: StagingSlice.reducer
}

export default configureStore({
    reducer,
    devTools: process.env.NODE_ENV !== 'production'
})