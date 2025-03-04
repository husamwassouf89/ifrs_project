import React, { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-multi-lang'

// Redux
import { useDispatch, useSelector } from 'react-redux'
import { client, clientsSlice, clientsState } from './ClientsSlice'

// API
import API from '../../services/api/api'

// Components
import TableActionBar from '../../components/TableActionBar/TableActionBar'
import { DashboardTable } from '../../components/Table/Table'
import { EllipsisLoader, WhiteboxLoader } from '../../components/Loader/Loader'
import { Link } from 'react-router-dom'

import './Clients.css'
import { ClassesMenu } from '../../components/PredefinedMenus/PredefinedMenus'
import { SelectField } from '../../components/FormElements/FormElements'
import { years } from '../../services/hoc/helpers'

interface IProps {
    category: 'facility' | 'financial';
    offbalance?: boolean;
    type?: 'limits';
}

export default (props: IProps) => {

    // Translation
    const t = useTranslation()

    // Redux
    const dispatch = useDispatch()
    const state = useSelector( ( state: { clients: clientsState } ) => state.clients )

    // Hooks
    const [keyword, setKeyword] = useState<string>("");
    const [classType, setClassType] = useState<any>();
    const [year, setYear] = useState<number>()
    const [quarter, setQuarter] = useState<'q1' | 'q2' | 'q3' | 'q4'>()
    const [classes, setClasses] = useState<any[]>([]);

    // API
    const ENDPOINTS = new API()

    // Table ref
    type TableHandle = React.ElementRef<typeof DashboardTable>;
    const tableRef = useRef<TableHandle>(null);
    
    // Search
    const search = (value: string) => {
        ENDPOINTS.abortCalls();
        tableRef.current?.reset()
        dispatch( clientsSlice.actions.reset() )
        setKeyword(value)
    }

    // Fetch Data
    const fetchData = (page: number, page_size: number = 10) => {
        console.log(classes);
        console.log(classType);
        dispatch( clientsSlice.actions.setIsFetching( true ) )

        ENDPOINTS.clients().index({ page, page_size, class_type_category: classType?.id ? undefined : props.category, class_type_id: classType?.id, year, quarter, type: props.offbalance ? 'documents' : undefined, limit: props.type === 'limits' ? 'yes' : undefined })
        .then((response: any) => {
            let clients: client[] = response.data.data.clients.map((client: any): client => ({
                id: client.id,
                loan_key: client.loan_key,
                cif: client.cif == '0' ? '00000' : client.cif,
                name: client.name,
                class_type: client.class_type_name,
                type: client.type
            }))
            
            dispatch( clientsSlice.actions.addClients( clients ) )
            dispatch( clientsSlice.actions.setHasMore( page < Number(response.data.data.last_page) ) )
            if( !state.isLoaded )
                dispatch( clientsSlice.actions.setIsLoaded( true ) )
        })
    }

    interface tableDataType { [key: string]: { [key: string]: any } }
    const generateData: () => tableDataType = () => {
        
        let data: tableDataType = {}
        state.clients.map((client, index) => {
            data[client.id] = {
                cif: client.cif,
                name: client.name,
                class_type: client.class_type,
                // type: client.type,
                actions: <div className="show-on-hover">
                            <Link target='_blank' to={ `/search-client?cif=${client.cif}` + (year ? `&year=${year}` : '') + (quarter ? `&quarter=${quarter}` : '') + ( props.type === 'limits' ? '&limits=yes' : '' ) + ( props.offbalance ? '&client_type=offbalance' : '' ) }><i className="icon-information" style={{ color: "#333" }} /></Link>
                        </div>
            }
        })

        return data
    }

    // First fetch
    useEffect(() => {
        if( state.isLoaded || state.isFetching )
            return;
        ENDPOINTS.other().predefined()
        .then((response: any) => {
            setClasses(response.data?.data?.class_types);
            // setClassType(response.data?.data?.class_types?.filter((item: any) => item.category === props.category)[0]);
        })
        return(() => {
            dispatch( clientsSlice.actions.setIsLoaded( false ) )
            dispatch( clientsSlice.actions.setIsFetching( false ) )
        })
    }, [])

    useEffect(() => {
        if(!state.isLoaded && classes?.length > 0)
            fetchData(1);
    }, [classes, classType]);

    useEffect(() => {
        ENDPOINTS.abortCalls();
        tableRef.current?.reset()
        dispatch( clientsSlice.actions.reset() )
    }, [classType, year, quarter, props.offbalance, location.href])

    useEffect(() => {
        // if(classes.filter((item: any) => item.category === props.category)[0] !== classType)
        //     setClassType(classes.filter((item: any) => item.category === props.category)[0]);
        ENDPOINTS.abortCalls();
        setClassType(null);
        tableRef.current?.reset()
        dispatch( clientsSlice.actions.reset() )
    }, [props.category]);

    return(
        <>
            { state.isLoaded ?
            <>
                { state.isLoading ? <WhiteboxLoader /> : ""}
                <form>
                    <div className="filters">
                        <div className="filter" key={props.category}>
                            <SelectField
                                isClearable
                                placeholder={t("class_type")}
                                value={classType}
                                onChange={(selected: any) => setClassType(selected)}
                                options={classes.filter((item: any) => item.category === props.category)}
                                getOptionLabel={(item: any) => item.name}
                                getOptionValue={(item: any) => item.id}
                                />
                        </div>
                        <div className="filter">
                            <SelectField isClearable defaultValue={year ? { label: year, value: year } : null} onChange={(selected: { value: number; }) => setYear(selected?.value)} placeholder={t("year")} options={years} />
                        </div>
                        <div className="filter">
                            <SelectField isClearable defaultValue={quarter ? { label: quarter?.toUpperCase(), value: quarter } : null} onChange={(selected: { value: 'q1' | 'q2' | 'q3' | 'q4'; }) => setQuarter(selected?.value)} placeholder={t("quarter")} options={[
                                { label: "Q1", value: "q1" },
                                { label: "Q2", value: "q2" },
                                { label: "Q3", value: "q3" },
                                { label: "Q4", value: "q4" }
                            ]} />
                        </div>
                    </div>
                </form>
                <TableActionBar
                    title={t("clients")}
                    search={search}
                    showFilter={false}
                    />
                
                <DashboardTable
                    ref={tableRef}
                    header={[ t("cif"), t("name"), t("class_type"), "" ]}
                    body={generateData()}
                    hasMore={state.hasMore}
                    loadMore={fetchData}
                    />

                
            </> : <div className="center"><EllipsisLoader /></div> }
        </>
    )

}